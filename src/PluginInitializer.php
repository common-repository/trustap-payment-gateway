<?php

namespace Trustap\PaymentGateway;

use Trustap\PaymentGateway\Gateway;
use Trustap\PaymentGateway\Controller\Settings;
use Trustap\PaymentGateway\Controller\Heartbeat;
use Trustap\PaymentGateway\Controller\Orders;

class PluginInitializer {

    /**
     * @var string
     */
    public $plugin_version;

    public function __construct($plugin_version)
    {
        $this->plugin_version = $plugin_version;
        $plugin_assets_url = plugins_url('', dirname(__FILE__, 1));
        define('TRUSTAP_IMAGE_URL', "{$plugin_assets_url}/assets/img/");
        define('TRUSTAP_STYLE_URL', "{$plugin_assets_url}/assets/style/");
        define('TRUSTAP_JS_URL', "{$plugin_assets_url}/assets/js/");
        define('TRUSTAP_TEMPLATE_PATH', __DIR__ . '/View/');

        add_filter('woocommerce_payment_gateways', array($this, 'trustap_add_gateway_class'));
        add_action('rest_api_init', array($this, 'trustap_register_settings_route'));
        add_action('rest_api_init', array($this, 'trustap_register_heartbeat_route'));
        add_action('rest_api_init', array($this, 'trustap_register_orders_route'));
        add_action('plugins_loaded', array($this, 'trustap_init_gateway_class'));
        add_action('init', array($this, 'register_styles'));
        add_action('init', array($this, 'register_scripts'));
    }

    public function trustap_add_gateway_class($gateways)
    {
        $gateways[] = 'Trustap\PaymentGateway\Gateway';
        return $gateways;
    }

    public function trustap_register_settings_route()
    {
        $controller = new Settings('trustap/v1');
        $controller->register_routes();
    }

    public function trustap_register_heartbeat_route()
    {
        $controller = new Heartbeat('trustap/v1', $this->plugin_version);
        $controller->register_routes();
    }

    public function trustap_register_orders_route()
    {
        $controller = new Orders('trustap/v1', $this->plugin_version);
        $controller->register_routes();
    }

    public function trustap_init_gateway_class()
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', array($this, 'no_woocommerce_trustap_notice'));
            \deactivate_plugins(dirname(__DIR__, 1) . '/trustap-gateway.php');
        } else {
            new Gateway();
        }
    }

    public function no_woocommerce_trustap_notice() {
        $message = esc_html__(
            __("You don't have WooCommerce installed.", 'trustap-payment-gateway')
        );
        echo "<div class=\"error\"><p>$message</p></div>";
    }

    public function register_styles()
    {
        wp_register_style(
            'trustap-checkout',
            TRUSTAP_STYLE_URL . 'checkout.css',
            array(),
            '1.0.0'
        );

        wp_register_style(
            'trustap-payment-settings',
            TRUSTAP_STYLE_URL . 'settings.css',
            array(),
            '1.0.0'
        );

        wp_enqueue_style('trustap-checkout');
        wp_enqueue_style('trustap-payment-settings');
    }

    public function register_scripts() {
        wp_register_script(
            'trustap-payment-settings',
            TRUSTAP_JS_URL . 'settings.js',
            '',
            '1.0.0'
        );

        wp_register_script(
            'trustap-payment-autocomplete',
            TRUSTAP_JS_URL . 'autocomplete.js',
            '',
            '1.0.0'
        );
    }
}
