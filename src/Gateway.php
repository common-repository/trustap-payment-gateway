<?php

namespace Trustap\PaymentGateway;

use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;
use WC_Payment_Gateway;
use WC_Product_Factory;
use Exception;
use Trustap\PaymentGateway\Core\Checkout;
use Trustap\PaymentGateway\Core\Service;
use Trustap\PaymentGateway\Core\Settings;
use Trustap\PaymentGateway\Core\Orders;
use Trustap\PaymentGateway\Helper\Validator;

class Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'trustap';
        $this->icon = TRUSTAP_IMAGE_URL . 'supported_cards.png';
        $this->method_title = __('Trustap Payments', 'trustap-payment-gateway');
        $this->method_description = __(
            'Use Trustap as your payment method.',
            'trustap-payment-gateway'
        );
        $this->supports = ['products'];
        $this->title = __(
            "Credit/Debit Card with Buyer Protection",
            'trustap-payment-gateway'
        );
        $this->description = __(
            "Trustap is a trusted third party that securely holds your money
            until your purchase is received.",
            'trustap-payment-gateway'
        );
        $this->confirm_handover = $this->get_option('confirm_handover');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        global $testmode;
        $testmode = $this->testmode;
        $this->payment_settings = new Settings();
        $this->init_form_fields();
        $this->handle_activation();
        $this->init_settings();
        $this->controller = new AbstractController('trustap/v1');
        $this->checkout = new Checkout($this->controller);
        $this->action_url = UriEnumerator::ACTION_PAGE_URL();
        add_action(
            "woocommerce_update_options_payment_gateways_{$this->id}",
            array($this, 'process_admin_options')
        );
        $this->orders = new Orders($this->controller);
        $this->service = new Service($this, $this->controller);
    }

    function handle_activation()
    {
        if (!$this->get_option('testmode') && !get_option('trustap_live_user_id')) {
            $this->update_option('enabled', false);
        }
    }

    /**
     * WooCommerce Payment Gateway settings form
     */

    public function init_form_fields()
    {
        $this->form_fields = $this->payment_settings::form_fields();
    }

    public function generate_trustap_login_html()
    {
        return $this->payment_settings->login_form();
    }

    /**
     * Description and supported card on checkout page
     */
    public function payment_fields()
    {
        $this->checkout->payment_fields($this->description);
    }

    /*
     * Checkout fields validation
     */
    public function validate_fields()
    {
        $this->checkout->validate_fields();
    }

    function get_billing_details($order_id) {
        $order = wc_get_order($order_id);

        $billing_details = array();

        if ($order) {
            $billing_details['line1'] = $order->get_billing_address_1();
            $billing_details['city'] = $order->get_billing_city();
            $billing_details['state'] = $order->get_billing_state();
            $billing_details['postcode'] = $order->get_billing_postcode();
            $billing_details['name'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $billing_details['country'] = $order->get_billing_country();
            $billing_details['phone'] = $order->get_billing_phone();
        }

        return $billing_details;
    }


    /*
     * We're processing the payments here
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        // we need it to get any order detailes
        $order = wc_get_order($order_id);


        $items = $woocommerce->cart->get_cart();
        $product_names = array();
        $payment_method = array();
        foreach ($items as $item => $values) {
            $_product = $values['data']->post;
            $product_names[] = $_product->post_title;
            $product_ID = $_product->ID;
            $_pf = new WC_Product_Factory();
            $product_single = $_pf->get_product($product_ID)->get_data();

            //Check if the product is a virtual or downloadable then set it to f2f model.
            if ($product_single['virtual'] || $product_single['downloadable']) {
                array_push($payment_method, "f2f");
            } else {
                array_push($payment_method, "online");
            }
        }
        //TODO: We need to test if local pick up is handled by this.
        // So far it seems this is the only way to check if there are any shipping methods and if
        // the item is actually going to be shipped by using those methods.
        $shippable = !empty(WC()->shipping->packages);

        if ($order->get_total() * 100 < 100) {
            wc_add_notice(
                __(
                    "In order to use Trustap Payment Gateway, total price of the order has to be larger than 1.00 " .
                    get_woocommerce_currency(),
                    'trustap-payment-gateway'
                ),
                'error'
            );
            return;
        }

        if (in_array("online", $payment_method) && in_array("f2f", $payment_method)) {
            wc_add_notice(
                __(
                    "We currently don't support different types of products in cart.
                    Please select only digital products and continue or choose only products
                    which will get delivered to you by post.",
                    'trustap-payment-gateway'
                ),
                'error'
            );
            return;
        } elseif (in_array("online", $payment_method) || $shippable === true) {
            // In the future we will move away from this method and use different url's.
            // Online.
            $GLOBALS['model'] = "";
        } elseif (in_array("f2f", $payment_method) && $shippable === false) {
            // F2F.
            $GLOBALS['model'] = "p2p/";
        }

        $allproductname = implode(", ", $product_names);

        $data = array(
            'price' => ($order->get_total()) * 100,
            'currency' => strtolower(get_woocommerce_currency())
        );

        try {
            $response = $this->controller->get_request($GLOBALS['model'] . 'charge', $data);
            $body = json_decode($response['body'], true);
            $_SESSION['charge'] = $body;
        } catch (Exception $error) {
            wc_add_notice($error);
        }

        $data = [
            'buyer_id' => Validator::sanitize_string(
                $_SESSION['buyer_id']
            ),
            'creator_role' => 'seller',
            'description' => 'Order ID ' . $order_id . ': ' . $allproductname,
            'currency' => Validator::sanitize_string(
                $_SESSION['charge']['currency']
            ),
            'charge_calculator_version' => Validator::sanitize_integer(
                $_SESSION['charge']['charge_calculator_version']
            ),
            'charge_seller' => Validator::sanitize_integer(
                $_SESSION['charge']['charge_seller']
            ),
            'seller_id' => $this->controller->seller_id
        ];
        if ($GLOBALS['model'] === 'p2p/') {
            $data['deposit_price'] = Validator::sanitize_integer(
                $_SESSION['charge']['price']
            );
            $data['deposit_charge'] = Validator::sanitize_integer(
                $_SESSION['charge']['charge']
            );
            $data['skip_remainder'] = true;
        } else {
            $data['price'] = Validator::sanitize_integer(
                $_SESSION['charge']['price']
            );
            $data['charge'] = Validator::sanitize_integer(
                $_SESSION['charge']['charge']
            );
        };
        $endpoint = $GLOBALS['model'] .
            'me/transactions/' .
            'create_with_guest_user';

        try {
            $response = $this->controller->post_request(
                $endpoint,
                $this->controller->seller_id,
                $data);
            $body = json_decode($response['body'], true);
            $_SESSION['transaction'] = $body;
            if (
                $order->meta_exists('trustap_transaction_ID' &&
                    $order->get_status !== 'payment')
            ) {
                return;
            } else {
                $order->update_meta_data(
                    'trustap_transaction_ID',
                    Validator::sanitize_integer($_SESSION['transaction']['id'])
                );
            }


            $order->update_meta_data('model', $GLOBALS['model']);
            $orderID = $order->id;

            $order->save();

            //Set up the token here then pass it via state.
            $action_token = md5(uniqid(mt_rand(), true));
            $_SESSION['token'] = $action_token;
            $billing_details = $this->get_billing_details($orderID);
            if ($GLOBALS['model'] === 'p2p/') {
                $state = "token={$action_token}:tx_type=p2p:order_id={$orderID}:name={$billing_details['name']}:line1={$billing_details['line1']}:city={$billing_details['city']}:state={$billing_details['state']}:postcode={$billing_details['postcode']}:country={$billing_details['country']}";

                $state = base64_encode($state);

                return array(
                    'result' => 'success',
                    'redirect' =>
                        $this->action_url .
                        'f2f/transactions/' .
                        Validator::sanitize_integer($_SESSION['transaction']['id']) .
                        '/pay_deposit?redirect_uri=' .
                        get_home_url() .
                        '/wc-api/trustap_webhook&state=' .
                        $state
                );
            } else {
                $this->service->send_shipping_details($orderID);
                $state = "token={$action_token}:tx_type=online:order_id={$orderID}:name={$billing_details['name']}:line1={$billing_details['line1']}:city={$billing_details['city']}:state={$billing_details['state']}:postcode={$billing_details['postcode']}:country={$billing_details['country']}";
                $state = base64_encode($state);
                return array(
                    'result' => 'success',
                    'redirect' =>
                        $this->action_url .
                        'online/transactions/' .
                        Validator::sanitize_integer($_SESSION['transaction']['id']) .
                        '/guest_pay?redirect_uri=' .
                        get_home_url() .
                        '/wc-api/trustap_webhook&state=' .
                        $state
                );
            }
        } catch (Exception $error) {
            wc_add_notice($error);
        }
    }
}
