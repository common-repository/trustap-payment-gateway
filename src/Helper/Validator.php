<?php

namespace Trustap\PaymentGateway\Helper;

use InvalidArgumentException;

class Validator {
    public static function email_address($value) {
        if (!is_email($value)) {
            throw new InvalidArgumentException('Not valid email address.');
        }

        return sanitize_email($value);
    }

    public static function sanitize_integer($value) {
        if (!is_int($value)) {
            throw new InvalidArgumentException('The value is not an integer.');
        }

        return (int)sanitize_textarea_field($value);
    }

    public static function sanitize_string($value) {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value is not a string.');
        }

        return sanitize_textarea_field($value);
    }

    public static function sanitize_url($value) {
        if (!is_string($value)) {
            throw new InvalidArgumentException('The value is not a string.');
        }

        return filter_var(sanitize_textarea_field($value), FILTER_SANITIZE_URL);
    }
}
