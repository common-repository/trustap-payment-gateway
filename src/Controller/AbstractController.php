<?php

namespace Trustap\PaymentGateway\Controller;

use Exception;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;

class AbstractController
{
    /**
     * @var string
     */
    public $namespace;

    public function __construct($namespace)
    {
        $this->namespace = $namespace;
        $this->trustap_api_url = UriEnumerator::API_URL();

        $mode = $GLOBALS['testmode'] ? 'test' : 'live';
        $this->seller_id = get_option("trustap_{$mode}_user_id");
        $this->api_key = get_option("trustap_{$mode}_api_key");
    }

    public function get_request($endpoint, $data)
    {
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' .
                    base64_encode($this->api_key . ':' . '')
            )
        );
        $url = $this->trustap_api_url . $endpoint . '?';
        if ($data) {
            $data_length = count($data);
            $i = 0;
            foreach ($data as $key => $value) {
                $i++;
                $url .= $key . '=' . $value;
                if ($i != $data_length) {
                    $url .=  '&';
                }
            }
        }
        $result = wp_remote_get($url, $args);
        if (is_wp_error($result)) {
            throw new Exception(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
        }
        return $result;
    }

    public function post_request($endpoint, $user_id, $data)
    {
        $url = $this->trustap_api_url . $endpoint;
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' .
                    base64_encode($this->api_key . ':' . ''),
                'Trustap-User' => $user_id
            ),
            'body' => json_encode($data)
        );
        $result = wp_remote_post($url, $args);
        if (is_wp_error($result)) {
            throw new Exception(
                __('Please try again.', 'trustap-payment-gateway'),
                'error'
            );
        }
        return $result;
    }

    public function get_request_headers()
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }

        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if ('HTTP_' === substr($name, 0, 5)) {
                $headers[
                    str_replace(
                        ' ',
                        '-',
                        ucwords(
                            strtolower(str_replace('_', ' ', substr($name, 5)))
                        )
                    )
                ] = $value;
            }
        }

        return $headers;
    }
}
