<?php

namespace Trustap\PaymentGateway\Enumerators;

defined('ABSPATH') || exit;

class Message
{
    static function render($message)
    {
        $messages = [
            'successful_login'  => __(
                'You logged in successfully!', 'trustap-payment-gateway'
            ),
            'successful_logout' => __(
                'You logged out successfully!', 'trustap-payment-gateway'
            )
        ];
        return $messages[$message];
    }
}
