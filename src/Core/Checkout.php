<?php

namespace Trustap\PaymentGateway\Core;

use Exception;
use Trustap\PaymentGateway\Helper\Validator;
use Trustap\PaymentGateway\Helper\Template;

class Checkout
{

    public function __construct($controller)
    {
        $this->controller = $controller;
    }

    /**
     * Description and image of the supported cards
     */
    public function payment_fields($description)
    {
        $args = ['description' => $description];
        $template = new Template();
        echo $template->render('/checkout', 'Description', $args);
    }

    /*
     * Checkout fields validation
     */
    public function validate_fields()
    {
        if (empty($_POST['billing_first_name'])) {
            wc_add_notice(
                __('First name is required!', 'trustap-payment-gateway'),
                'error'
            );
            return false;
        }
        if (empty($_POST['billing_last_name'])) {
            wc_add_notice(
                __('Last name is required!', 'trustap-payment-gateway'),
                'error'
            );
            return false;
        }
        if (empty($_POST['billing_email'])) {
            wc_add_notice(
                __('Email is required!', 'trustap-payment-gateway'),
                'error'
            );
            return false;
        if (empty($_POST['billing_country'])) {
           wc_add_notice(
             __('Country is required!', 'trustap-payment-gateway'),
             'error'
           );
        }
        }
        if (!$this->trustap_tos_accepted()) {
            wc_add_notice(
                __(
                    '<strong>Please accept Trustap Terms of Use</strong>',
                    'trustap-payment-gateway'
                ),
                'error'
            );
        }

        $time = time();
        $data = [
            'email' => Validator::email_address($_POST['billing_email']),
            'first_name' => Validator::sanitize_string(
                $_POST['billing_first_name']
            ),
            'last_name' => Validator::sanitize_string(
                $_POST['billing_last_name']
            ),
            'country_code' => Validator::sanitize_string(
                $_POST['billing_country']
            ),
            'tos_acceptance' => [
                'unix_timestamp' => $time,
                'ip' => Validator::sanitize_string($_SERVER['REMOTE_ADDR'])
            ]
        ];

        try {
            $response = $this->controller->post_request('guest_users', '', $data);
            $body = json_decode($response['body'], true);
            $_SESSION['buyer_id'] = Validator::sanitize_string($body['id']);
            return true;
        } catch (Exception $error) {
            wc_add_notice(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
        }
    }

    private function trustap_tos_accepted()
    {
        return $_POST['tos_checkbox'] && $_POST['payment_method'] === 'trustap';
    }
}
