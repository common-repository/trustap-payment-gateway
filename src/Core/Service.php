<?php

namespace Trustap\PaymentGateway\Core;

use Exception;
use Trustap\PaymentGateway\Helper\Validator;
use Automattic\WooCommerce\Utilities\OrderUtil;

class Service
{
    public function __construct($wc_payment_gateway, $controller)
    {
        $this->wc_payment_gateway = $wc_payment_gateway;
        $this->controller = $controller;
        $this->seller_id = $controller->seller_id;

        $mode = $GLOBALS['testmode'] ? 'test' : 'live';
        $this->username = get_option("trustap_{$mode}_username");
        $this->password = get_option("trustap_{$mode}_password");

        add_action(
            'woocommerce_api_trustap_webhook',
            array($this, 'webhook_handler')
        );
    }

    public function webhook_handler()
    {
        $this->handle_cancle_on_actions_page();
        $this->handle_incoming_post_requests();
        $this->handle_incoming_get_requests();
        $this->cleanup_step();
        die();
    }

    private function handle_cancle_on_actions_page()
    {
        if (isset($_GET['code']) && $_GET['code'] === 'cancelled') {
            return wp_redirect(wc_get_checkout_url());
        }
    }

    private function handle_incoming_post_requests()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $request_body = file_get_contents('php://input');
            $request_headers = array_change_key_case(
                $this->controller->get_request_headers(),
                CASE_UPPER
            );
            if (empty($request_headers) || empty($request_body)) {
                wc_add_notice(
                    __('Please try again.', 'trustap-payment-gateway'),
                    'error'
                );
                exit();
            }

            $body_webhook = json_decode($request_body);

            if (strpos($body_webhook->code, 'p2p') !== false) {
                return $this->p2p_webhook();
            } else {
                return $this->online_webhook();
            }
        }
    }

    private function handle_incoming_get_requests()
    {
        if (
            isset($_GET['trustap_status']) &&
            isset($_GET['code']) &&
            isset($_GET['state'])
        ) {
            $state = Validator::sanitize_string($_GET['state']);
            $state = base64_decode($state);
            $state = explode(':', $state);
            $type = explode('=', $state[1]);
            if (
                $_GET['trustap_status'] !== 'ok' ||
                ($_GET['code'] !== 'processing' && $_GET['code'] !== 'paid')
            ) {
                exit();
            }

            if ($_GET['code'] === 'processing') {

                $this->display_payment_review_screen();
            }

            if (!isset($_GET['tx_id'])) {
                exit();
            }
            if ($type[1] === "p2p") {
                return $this->p2p_webhook();
            } else {
                $this->online_webhook();
            }
        }
    }

    private function preformat_incoming_post_request()
    {
        $request_body = file_get_contents('php://input');

        if (
            $this->username !== $_SERVER['PHP_AUTH_USER'] ||
            $this->password !== $_SERVER['PHP_AUTH_PW']
        ) {
            wc_add_notice(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
            return;
        }

        return json_decode($request_body);
    }

    private function get_transaction($type, $transaction_id)
    {
        $prefix = '';
        if ($type === 'p2p') {
            $prefix = 'p2p';
        }

        try {
            $response = $this->controller->get_request(
                "{$prefix}/transactions/{$transaction_id}",
                ''
            );
        } catch (Exception $error) {
            wc_add_notice($error);
            return false;
        }

        $body = json_decode($response['body'], true);
        return $body;
    }

    private function is_deposit_paid($transaction_id)
    {
        $transaction = $this->get_transaction('p2p', $transaction_id);
        if (!$transaction['deposit_paid']) {
            wc_add_notice(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
            return false;
        }
        return true;
    }

    private function delivery_handler()
    {
        $body_webhook = $this->preformat_incoming_post_request();
        if ($body_webhook->code === "basic_tx.delivered") {
            $transaction_id = Validator::sanitize_string(
                $body_webhook->target_id
            );

            try {
                $response = $this->controller->get_request(
                    "transactions/{$transaction_id}",
                    ''
                );
            } catch (Exception $error) {
                wc_add_notice($error);
            }

            $body = json_decode($response['body'], true);
            if (!$body['delivered']) {
                wc_add_notice(
                    __('Please try again.', 'trustap-payment-gateway'),
                    'error'
                );
                return;
            }

            $order = $this->get_order_by_transaction_id($transaction_id);
            $order->update_status('completed');
        }
    }

    private  function display_payment_review_screen()
    {

        $transaction_id = Validator::sanitize_string($_GET['tx_id']);
        $order_raw = $this->get_order_by_transaction_id($transaction_id);
        $order_data = json_decode($order_raw, true);
        $order_id = $order_data['id'];
        include(plugin_dir_path(__FILE__) . '../View/payment_review/payment_review.php');
    }

    private function accept_deposit($transaction_id, $order)
    {
        try {
            $this->controller->post_request(
                "p2p/transactions/{$transaction_id}/accept_deposit",
                $this->seller_id,
                ''
            );
        } catch (Exception $exception) {
            $order->add_order_note(
                __('Accept deposit manually.', 'trustap-payment-gateway'),
                false
            );
            return wp_redirect($this->wc_payment_gateway->get_return_url($order));
        }
    }

    private function handle_handover_confirmation($transaction_id, $order)
    {
        if ($this->wc_payment_gateway->confirm_handover === 'manually') {
            $order->update_status('handoverpending');
        } else {
            $this->confirm_handover($transaction_id, $order);
        }
    }

    public function confirm_handover($transaction_id, $order)
    {
        try {
            $this->controller->post_request(
                "p2p/transactions/{$transaction_id}/confirm_handover",
                $this->seller_id,
                ''
            );
        } catch (Exception $exception) {
            $order->add_order_note(
                __('Confirm handover manually.', 'trustap-payment-gateway'),
                false
            );
            return wp_redirect($this->wc_payment_gateway->get_return_url($order));
        }
    }

    private function handle_refund($tx_type)
    {
        $body_webhook = $this->preformat_incoming_post_request();
        if (
            $body_webhook->code === "p2p_tx.funds_refunded" ||
            $body_webhook->code === "basic_tx.deposit_refunded"
        ) {
            $transaction_id = Validator::sanitize_string(
                $body_webhook->target_id
            );

            $body = $this->get_transaction($tx_type, $transaction_id);
            if ($body['released_to_seller']) {
                return;
            }

            $order = $this->get_order_by_transaction_id($transaction_id);
            $order->update_status('refunded');
        }
    }

    private function cancel_order($transaction_id)
    {
        global $woocommerce;
        $order = $this->get_order_by_transaction_id($transaction_id);
        $order->update_status('cancelled');
    }

    private function is_paid($transaction_id)
    {
        $transaction = $this->get_transaction('online', $transaction_id);

        if (!$transaction['paid']) {
            wc_add_notice(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
            return false;
        }

        return true;
    }

    public function p2p_webhook()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $transaction_id = (int) Validator::sanitize_string($_GET['tx_id']);
                if (!$this->is_deposit_paid($transaction_id)) {
                    exit();
                }

                global $woocommerce;
                $order = $this->get_order_by_transaction_id($transaction_id);
                $order->payment_complete();
                $order->add_order_note(
                    __('Paid and confirmed', 'trustap-payment-gateway'),
                    true
                );
                $woocommerce->cart->empty_cart();
                $this->accept_deposit($transaction_id, $order);
                $this->handle_handover_confirmation($transaction_id, $order);

                return wp_redirect($this->wc_payment_gateway->get_return_url($order));
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

                $body_webhook = $this->preformat_incoming_post_request();

                $this->handle_refund('p2p');

                if (
                    $body_webhook->code !== "p2p_tx.deposit_paid" &&
                    $body_webhook->code !== "p2p_tx.cancelled" &&
                    $body_webhook->code !== "p2p_tx.buyer_handover_confirmed" &&
                    $body_webhook->code !== "p2p_tx.seller_handover_confirmed"
                ) {
                    wc_add_notice(
                        __('Please try again.', 'trustap-payment-gateway'),
                        'error'
                    );
                    exit();
                }

                $transaction_id = Validator::sanitize_string(
                    $body_webhook->target_id
                );

                if ($body_webhook->code === "p2p_tx.cancelled") {
                    $this->cancel_order($transaction_id);
                    exit();
                }

                if (!$this->is_deposit_paid($transaction_id)) {
                    exit();
                }

                global $woocommerce;
                $order = $this->get_order_by_transaction_id($transaction_id);

                if ($body_webhook->code === "p2p_tx.deposit_paid") {
                    $order->payment_complete();
                    $order->add_order_note(
                        __('Paid and confirmed', 'trustap-payment-gateway'),
                        true
                    );
                }

                if (
                    $body_webhook->code === "p2p_tx.buyer_handover_confirmed" ||
                    $body_webhook->code === "p2p_tx.seller_handover_confirmed"
                ) {
                    $order->update_status('handoverconfirmed');
                    exit();
                }

                $this->accept_deposit($transaction_id, $order);
                $this->handle_handover_confirmation($transaction_id, $order);

                status_header(200);
            }
        } catch (Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('p2p_webhook error has occured: ' . $e->getMessage(), array('source' => 'trustap_payment_gateway'));
            }
                wc_add_notice(
                    __('An error occurred. Please try again later.', 'trustap-payment-gateway'),
                    'error'
                );

            };

    }

    public function online_webhook()
    {
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {

                $transaction_id = (int)Validator::sanitize_string($_GET['tx_id']);
                if (!$this->is_paid($transaction_id)) {
                    exit();
                }

                global $woocommerce;
                $order = $this->get_order_by_transaction_id($transaction_id);
                $order->payment_complete();
                $woocommerce->cart->empty_cart();
                return wp_redirect($this->wc_payment_gateway->get_return_url($order));
            } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $body_webhook = $this->preformat_incoming_post_request();

                if (
                    $body_webhook->code !== "basic_tx.paid" &&
                    $body_webhook->code !== "basic_tx.delivered" &&
                    $body_webhook->code !== "basic_tx.funds_released" &&
                    $body_webhook->code !== "basic_tx.cancelled" &&
                    $body_webhook->code !== "basic_tx.deposit_refunded"
                ) {
                    wc_add_notice(
                        __('Please try again.', 'trustap-payment-gateway'),
                        'error'
                    );
                    throw new Exception("Invalid webhook code received: {$body_webhook->code}");
                }

                $this->handle_refund('online');
                $this->delivery_handler();

                $transaction_id = Validator::sanitize_string($body_webhook->target_id);

                if ($body_webhook->code === "basic_tx.cancelled") {
                    $this->cancel_order($transaction_id);
                    exit();
                }

                if (!$this->is_paid($transaction_id)) {
                    exit();
                }

                global $woocommerce;
                $order = $this->get_order_by_transaction_id($transaction_id);
                $order->payment_complete();
            }
            status_header(200);
        } catch (Exception $e) {
            if (function_exists('wc_get_logger')) {
                $logger = wc_get_logger();
                $logger->error('online_webhook error has occured: ' . $e->getMessage(), array('source' => 'trustap_payment_gateway'));
            }
            wc_add_notice(
                __('An error occurred. Please try again later.', 'trustap-payment-gateway'),
                'error'
            );
        };
    }

    public static function get_order_by_transaction_id($transaction_id)
    {
        global $wpdb;
        $order_id = null;
        if (OrderUtil::custom_orders_table_usage_is_enabled()) {
            $order_id = $wpdb->get_var(
                $wpdb->prepare(
                    "
            SELECT order_id 
            FROM {$wpdb->prefix}wc_orders_meta 
            WHERE meta_key = 'trustap_transaction_ID' 
            AND meta_value = %s
            ",
                    $transaction_id
                )
            );


        } else {
            $order_id = $wpdb->get_var(
                $wpdb->prepare(
                    "
                    SELECT DISTINCT ID FROM
                    $wpdb->posts as posts
                    LEFT JOIN $wpdb->postmeta as meta
                        ON posts.ID = meta.post_id
                        WHERE meta.meta_value = %s
                        AND meta.meta_key = %s
                ",
                    $transaction_id,
                    'trustap_transaction_ID'
                )
            );

        }
        if (empty($order_id)) {
            return false;
        }

        return wc_get_order($order_id);
    }
    public function send_shipping_details($order_id)
    {
        $order = wc_get_order($order_id);
        $shipping_name = $order->get_shipping_first_name() . " " . $order->get_shipping_last_name();
        $shipping_phone = $order->get_billing_phone();
        $shipping_address_1 = $order->get_shipping_address_1();
        $shipping_address_2 = $order->get_shipping_address_2();
        $shipping_city = $order->get_shipping_city();
        $shipping_state = $order->get_shipping_state();
        $shipping_postcode = $order->get_shipping_postcode();
        $shipping_country = $order->get_shipping_country();
        $transaction_id = $order->get_meta('trustap_transaction_ID');

        $data = [
            'name' => $shipping_name,
            'phone' => $shipping_phone,
            'city' => $shipping_city,
            'postal_code' => $shipping_postcode,
            'state' => $shipping_state,
            'address_line_1' => $shipping_address_1,
            'address_line_2' => $shipping_address_2,
            'country' => $shipping_country
        ];

        $raw_response = $this->controller->post_request(
            "/transactions/{$transaction_id}/shipping_details",
            $this->seller_id,
            $data
        );

        $response_status = json_decode($raw_response['response']['code']);
        $response_body = json_decode($raw_response['body']);
        if ($response_status != 200) {
            wp_send_json($response_body, 500);
            return;
        }
    }

    public function cleanup_step(): void
    {
        if (!is_plugin_active('woocommerce-shipmate/woocommerce-shipmate.php')) {
            return;
        }
        $this->send_tracking();
    }

    public function  send_tracking(): void
    {
        $orders = wc_get_orders(array(
            'status' => 'processing',
        ));

        foreach ($orders as $order) {
            $carrier = $order->get_meta('_shipmate_carrier');
            $tracking_code = $order->get_meta('_shipmate_tracking_reference');
            $transaction_id = $order->get_meta('trustap_transaction_ID');
            $data = [
                'tracking_code' => $tracking_code,
                'carrier' => $carrier
            ];
            $response_status = 'N/A';
            $message = 'No message';
            if (empty($tracking_code)) {
                continue;
            }
            try {
                $raw_response = $this->controller->post_request(
                    "transactions/{$transaction_id}/track",
                    $this->seller_id,
                    $data
                );
                $response = json_decode($raw_response['body']);
                $response_status = json_decode($raw_response['response']['code']);
                $message = $response->code;
                if ($response_status !=200) {
                    $order->add_order_note('Oops something went wrong. Please contact Trustap Support with the following transaction ID: ' . $transaction_id );
                    continue;
                }
                $order->update_status("wc-shipped");
            } catch (Exception $error) {
                error_log("Error with status $response_status . Message: $message");
            }
        }
    }
}
    
