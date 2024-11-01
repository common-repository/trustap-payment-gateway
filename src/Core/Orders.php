<?php

namespace Trustap\PaymentGateway\Core;

use Trustap\PaymentGateway\Helper\Template;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;

class Orders
{
    public function __construct($controller)
    {
        $this->controller = $controller;
        $this->register_order_statuses();
        add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
        add_action('add_meta_boxes', array($this, 'add_shipping_details_meta_box'));
        add_action('add_meta_boxes', array($this, 'add_confirm_handover_meta_box'));
    }

    public function add_shipping_details_meta_box()
    {
        global $post;
        $order = wc_get_order($post->ID);

        if ($order) {
            if (strpos($order->get_meta('model'), "p2p/") !== false) {
                return;
            }
            if ($order->get_payment_method() !== 'trustap') {
                return;
            }
            add_meta_box(
                'trustap-shipping-meta-box',
                'Trustap Shipment Tracking',
                array($this, 'shipping_details_meta_box'),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    public function add_confirm_handover_meta_box()
    {
        global $post;
        $order = wc_get_order($post->ID);

        if (!$order) {
            return;
        }
        if (strpos($order->get_meta('model'), "p2p/") === false) {
            return;
        }
        if ($order->get_payment_method() !== 'trustap') {
            return;
        }
        if (!$order->has_status('handoverpending')) {
            return;
        }
        add_meta_box(
            'trustap-confirm-handover-meta-box',
            'Trustap Handover',
            array($this, 'confirm_handover_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    public function shipping_details_meta_box()
    {
        global $post;
        $order = wc_get_order($post->ID);

        $shipping_carriers = $this->controller->get_request('supported_carriers/', []);
        $shipping_carriers = json_decode($shipping_carriers['body'], true);
        $shipping_carriers = array_merge($shipping_carriers, [
            [
                'code' => 'other',
                'name' => 'Other'
            ]
        ]);
        $template = new Template();
        if ($order->has_status('shipped')) {
            $args = [
                'icon' => TRUSTAP_IMAGE_URL . 'truck-fast-solid.svg',
                'img_alt' => __('Delivery truck', 'trustap-payment-gateway'),
                'message' => __(
                    '
          The order is shipped successfully. The order status will change
          into "Delivered" when the order is being delivered.',
                    'trustap-payment-gateway'
                )
            ];
            echo $template->render('settings', 'ShippingDetailsMessage', $args);
        } elseif ($order->has_status('completed')) {
            $args = [
                'icon' => TRUSTAP_IMAGE_URL . 'rounded_checkmark_icon.svg',
                'img_alt' => __('Checkmark icon', 'trustap-payment-gateway'),
                'message' => __(
                    '
          This order is delivered successfully.',
                    'trustap-payment-gateway'
                )
            ];
            echo $template->render('settings', 'ShippingDetailsMessage', $args);
        } else {
            $args = [
                'shipping_carriers' => $shipping_carriers,
                'save_tracking_details_url' => get_rest_url() .
                'trustap/v1/save-tracking-details',
                'nonce' => wp_create_nonce('wp_rest')
            ];
            echo $template->render('settings', 'ShippingDetailsForm', $args);
        }
    }

    public function confirm_handover_meta_box()
    {
        $template = new Template();
        $args = [
            'icon' => TRUSTAP_IMAGE_URL . "handshake-simple-solid.svg",
            'confirm_handover_url' => UriEnumerator::CONFIRM_HANDOVER_URL(),
            'nonce' => wp_create_nonce('wp_rest')
        ];
        echo $template->render('settings', 'ConfirmHandover', $args);
    }

    public static function register_order_statuses()
    {
        $order_statuses = [
            'wc-shipped' => [
                'label' => 'Shipped',
                'public' => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list' => true,
                'exclude_from_search' => false
            ],
            'wc-handoverpending' => [
                'label' => 'Handover pending',
                'public' => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list' => true,
                'exclude_from_search' => false
            ],
            'wc-handoverconfirmed' => [
                'label' => 'Handover confirmed',
                'public' => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list' => true,
                'exclude_from_search' => false
            ]
        ];

        foreach ($order_statuses as $key => $status) {
            register_post_status($key, $status);
        }
    }

    public function add_order_statuses($order_statuses)
    {
        $new_order_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-completed' === $key) {
                $new_order_statuses['wc-shipped'] = __(
                    'Shipped',
                    'trustap-payment-gateway'
                );
                $new_order_statuses['wc-handoverpending'] = __(
                    'Handover pending',
                    'trustap-payment-gateway'
                );
                $new_order_statuses['wc-handoverconfirmed'] = __(
                    'Handover confirmed',
                    'trustap-payment-gateway'
                );
            }
        }
        return $new_order_statuses;
    }
}
