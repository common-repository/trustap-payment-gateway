<?php

namespace Trustap\PaymentGateway\Controller;

use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Helper\Validator;

class Heartbeat extends AbstractController
{
    /**
     * @var string
     */
    public $namespace;

    /**
     * @var string
     */
    public $plugin_version;
    
    public function __construct($namespace, $plugin_version)
    {
        parent::__construct($namespace);

        $this->plugin_version = $plugin_version;
    }
    public function register_routes()
    {
        register_rest_route($this->namespace, '/heartbeat', array(
            'methods' => 'GET',
            'callback'  => array($this, 'heartbeat'),
            'permission_callback' => '__return_true'
        ));
    }

    public function heartbeat()
    {
        global $wp_version;

        wp_send_json(
            [
                'version' => Validator::sanitize_string($this->plugin_version),
                'server' => Validator::sanitize_url($_SERVER['SERVER_NAME']),
                'php' => Validator::sanitize_string(phpversion()),
                'wordpress' => Validator::sanitize_string($wp_version),
                'woocommerce' => Validator::sanitize_string(get_option('woocommerce_version'))
            ],
            200
        );
    }
}
