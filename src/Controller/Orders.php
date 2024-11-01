<?php

namespace Trustap\PaymentGateway\Controller;

use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Helper\Validator;

class Orders extends AbstractController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/save-tracking-details', array(
            'methods' => 'POST',
            'callback' => array($this, 'save_tracking_details'),
            'permission_callback' => function() {
                return current_user_can('administrator');
            }
        ));
        register_rest_route($this->namespace, '/confirm-handover', array(
            'methods' => 'GET, POST',
            'callback' => array($this, 'confirm_handover'),
            'permission_callback' => function() {
                return current_user_can('administrator');
            }
        ));
    }

    public function save_tracking_details($request)
    {
        $carrier = $request->get_param('carrier');
        $carrier = Validator::sanitize_string($carrier);

        $tracking_code = $request->get_param('trackingCode');
        $tracking_code = Validator::sanitize_string($tracking_code);

        if ($carrier === "other") {
            $carrier = sanitize_textarea_field(
                $request->get_param('otherCarrier')
            );
        }

        $data = [
            'tracking_code' => $tracking_code,
            'carrier' => $carrier
        ];

        $order_id = $request->get_param('orderId');
        $order_id = Validator::sanitize_string($order_id);

        $order = wc_get_order($order_id);
        $transaction_id = $order->get_meta('trustap_transaction_ID');

        $raw_response = $this->post_request(
            "transactions/{$transaction_id}/track",
            $this->seller_id,
            $data
        );
        $response = json_decode($raw_response['body']);
    
        if (!array_key_exists("status", $response)) {
            wp_send_json($response, 500);
            return;
        }
        if ($response->status !== "tracked") {
            wp_send_json($response, 500);
            return;
        }
        $order->update_status('shipped');
        wp_send_json($response, 200);
    }

    public function confirm_handover($request)
    {
        $order_id = $request->get_param('orderId');
        $order = wc_get_order($order_id);
        $transaction_id = $order->get_meta('trustap_transaction_ID');

        $data = ['transactionId' => $transaction_id];
        $raw_response = $this->post_request(
            "/p2p/transactions/{$transaction_id}/confirm_handover",
            $this->seller_id,
            $data
        );

        $response_status = json_decode($raw_response['response']["code"]);
        $response_body = json_decode($raw_response['body']);
        if ($response_status != 200) {
            wp_send_json($response_body, 500);
            return;
        }
        $order = wc_get_order($order_id);
        $order->update_status('handoverconfirmed');
    }
}
