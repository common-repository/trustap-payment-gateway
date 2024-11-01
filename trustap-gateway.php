<?php

namespace Trustap\PaymentGateway;

require_once __DIR__ . '/vendor/autoload.php';

use Trustap\PaymentGateway\PluginInitializer;


session_start();

if (! defined('ABSPATH')) {
    exit;
}

/* 
 * When updating this value, don't forget to update the value in the DocBlock
 */
$version = "1.4.1";

/*
 * Plugin Name: Trustap - WooCommerce Payment Gateway
 * Plugin URI: https://www.trustap.com/
 * Description: Take credit card payments on your store using Trustap.
 * Author: Trustap Ltd
 * Author URI: https://www.trustap.com/
 * Version: 1.4.1
 */

new PluginInitializer($version);
