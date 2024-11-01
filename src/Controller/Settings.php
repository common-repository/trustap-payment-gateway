<?php

namespace Trustap\PaymentGateway\Controller;

use Trustap\PaymentGateway\OpenIDConnect;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;
use Trustap\PaymentGateway\Controller\AbstractController;
use Trustap\PaymentGateway\Helper\Validator;

class Settings extends AbstractController
{
    public function register_routes()
    {
        register_rest_route($this->namespace, '/oidc-test-login', array(
            'methods' => 'GET, POST',
            'callback'  => array($this, 'oidcAuthenticateTest'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/oidc-live-login', array(
            'methods' => 'GET, POST',
            'callback'  => array($this, 'oidcAuthenticateLive'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/oidc-logout', array(
            'methods' => 'GET',
            'callback' => array($this, 'oidcLogout'),
            'permission_callback' => function() {
                return current_user_can('administrator');
            }
        ));
    }

    /**
     * Completes an OIDC authentication by adding the user's authentication
     * details to the current session.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function oidcAuthenticateTest($request)
    {
        $credentials = $request->get_param('trustap_credentials');
        if ($credentials) {
            $this->decodeAndSaveCredentials($credentials, 'test');
        }

        $this->handleOidcFlow('test');
    }

    /**
     * Completes an OIDC authentication by adding the user's authentication
     * details to the current session.
     *
     * @param WP_REST_Request $request Current request.
     */
    public function oidcAuthenticateLive($request)
    {
        $credentials = $request->get_param('trustap_credentials');
        if ($credentials) {
            $this->decodeAndSaveCredentials($credentials, 'live');
        }

        $this->handleOidcFlow('live');
    }

    public function decodeAndSaveCredentials($base64_credentials , $environment) {
        if (!is_string($base64_credentials )) {
            exit;
        }
        $sanitized_credentials = sanitize_textarea_field($base64_credentials );
        $credentials = json_decode(base64_decode($sanitized_credentials));
        $this->saveCredentials($credentials, $environment);
        wp_send_json(['data' => 'success'], 200);
    }

    public function handleOidcFlow($environment) {
        $oidc = new OpenIDConnect($environment);
        $oidc->logIn("basic_tx:offline_create_join basic_tx:offline_track p2p_tx:offline_create_join p2p_tx:offline_accept_deposit p2p_tx:offline_confirm_handover");
        $settings_uri =
            admin_url() .
            UriEnumerator::PAYMENT_GATEWAY_SETTINGS;

        if (wp_redirect($settings_uri)) {
            exit;
        }
    }

    /**
     * Handles OIDC Logout
     *
     * @param WP_REST_Request $request Current request.
     */
    public static function oidcLogout($request)
    {
        $environment = $request->get_param('environment');
        $environment = Validator::sanitize_string($environment);

        $oidc = new OpenIDConnect($environment);
        $oidc->logOut($environment);
    }

    public static function saveCredentials($credentials, $environment)
    {
        $provider_url = $environment === 'test' ?
            UriEnumerator::SSO_PROVIDER_TEST :
            UriEnumerator::SSO_PROVIDER_LIVE;
        update_option("trustap_{$environment}_username", $credentials->Username);
        update_option("trustap_{$environment}_password", $credentials->Password);
        update_option("trustap_{$environment}_api_key", $credentials->apiKey);
        update_option("trustap_{$environment}_client_id", $credentials->clientID);
        update_option("trustap_{$environment}_client_secret", $credentials->clientSecret);
        update_option("trustap_{$environment}_provider_url", $provider_url);
    }
}
