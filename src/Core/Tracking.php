<?php

namespace Trustap\PaymentGateway\Core;

use Trustap\PaymentGateway\Helper\Template;

class Tracking {
    public function __construct($controller)
    {
        $this->controller = $controller;
        add_filter('wc_order_statuses', array($this, 'add_order_statuses'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
    }

    public function add_meta_box()
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
                array($this, 'meta_box'),
                'shop_order',
                'side',
                'high'
            );
        }
    }

    public function meta_box()
    {
        global $post;
        $order = wc_get_order($post->ID);
        $orderStatus = $order->get_status();

        $shipping_carriers = $this->controller->get_request('supported_carriers/', []);
        $shipping_carriers = json_decode($shipping_carriers['body'], true);
        $shipping_carriers = array_merge($shipping_carriers, [[
            'code' => 'other',
            'name' => 'Other'
        ]
        ]);
        $template = new Template();
        if ($orderStatus === 'shipped') {
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
        } elseif ($orderStatus === 'completed') {
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
                    'trustap/v1/save-tracking-details'
            ];
            echo $template->render('settings', 'ShippingDetailsForm', $args);
        }
    }

    public static function register_order_statuses()
    {
        $order_statuses = [
            'wc-shipped' => [
                'label' => 'Shipped',
                'public' => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false
            ],
            'wc-delivered' => [
                'label' => 'Delivered',
                'public' => true,
                'show_in_admin_status_list' => true,
                'show_in_admin_all_list'    => true,
                'exclude_from_search'       => false
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
                $new_order_statuses['wc-shipped'] = __('Shipped', 'trustap');
                $new_order_statuses['wc-delivered'] = __('Delivered', 'trustap');
            }
        }
        return $new_order_statuses;
    }
}