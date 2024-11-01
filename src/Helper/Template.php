<?php

namespace Trustap\PaymentGateway\Helper;

class Template
{
    public function render(
        string $path,
        string $file,
        array $args = []
    ) {
        ob_start();
        extract($args);
        include($this->template($path, $file));
        return ob_get_clean();
    }

    private static function template(?string $path, string $file): string
    {
        return TRUSTAP_TEMPLATE_PATH . $path . '/' . $file . '.php';
    }
}
