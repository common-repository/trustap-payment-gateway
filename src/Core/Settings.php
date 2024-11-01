<?php

namespace Trustap\PaymentGateway\Core;

use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;
use Trustap\PaymentGateway\Helper\Template;

class Settings
{
    /**
     * WooCommerce Payment Gateway settings form
     */
    public static function form_fields()
    {
        return [
            'enabled' => [
                'title' => __(
                    'Enable/Disable',
                    'trustap-payment-gateway'
                ),
                'label' => __(
                    'Enable Trustap Payments',
                    'trustap-payment-gateway'
                ),
                'type' => 'checkbox',
                'description' => 'Enable Trustap payment gateway on checkout',
                'default' => 'no',
                'desc_tip' => true,
            ],
            'testmode' => [
                'title' => __('Test mode', 'trustap-payment-gateway'),
                'label' => __(
                    'Enable Test Mode',
                    'trustap-payment-gateway'
                ),
                'type' => 'checkbox',
                'description' => __(
                    'Place the payment gateway in test mode using test API keys.',
                    'trustap-payment-gateway'
                ),
                'default' => 'yes',
                'desc_tip' => true,
            ],
            'confirm_handover' => [
                'type' => 'select',
                'title' => __(
                    'Confirm Handover for Virtual Items',
                    'trustap-payment-gateway'
                ),
                'description' => __(
                    "Automatic handover confirmation will confirm the product
                     has been delivered to the buyer and start the complaints
                     period once the buyer submits the payment. You can choose
                     manual handover confirmation if you require longer to
                     deliver products to the buyer. Please be aware that
                     manual handover confirmation will require handover to be
                     confirmed for each individual order.
                    ",
                    'trustap-payment-gateway'
                ),
                'options' => [
                    'automatically' => __(
                        'Automatically (Immediately)',
                        'trustap-payment-gateway'
                    ),
                    'manually' => __(
                        'Manually',
                        'trustap-payment-gateway'
                    )
                ],
                'default' => 'automatically',
                'required' => true,
                'desc_tip' => true,
                'custom_attributes' => [
                    'onchange' => 'handleConfirmationPeriodInput()'
                ],
            ],
            'trustap_login' => [
                'type' => 'trustap_login',
            ]
        ];
    }

    public function login_form()
    {
        $template = new Template();
        $args = array(
            'oidc_test_login_uri' => UriEnumerator::OIDC_TEST_LOGIN(),
            'oidc_live_login_uri' => UriEnumerator::OIDC_LIVE_LOGIN(),
            'logout_uri' => UriEnumerator::OIDC_LOGOUT() .
            wp_create_nonce('wp_rest'),
            'title' => __(
                'Test Trustap Key',
                'trustap-payment-gateway'
            ),
            'is_logged_id' => $this->is_trustap_seller_linked(),
            'icons' => [
                'info' => TRUSTAP_IMAGE_URL . 'circle-info-solid.svg',
                'checkmark' => TRUSTAP_IMAGE_URL . 'rounded_checkmark_icon.svg'
            ]
        );
        return $template->render('settings', 'TrustapKeyForm', $args);
    }

    private function is_trustap_seller_linked()
    {
        return [
            "test" => get_option('trustap_test_user_id'),
            "live" => get_option('trustap_live_user_id')
        ];
    }
}