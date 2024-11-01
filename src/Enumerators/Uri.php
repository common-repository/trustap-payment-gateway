<?php

namespace Trustap\PaymentGateway\Enumerators;

defined('ABSPATH') || exit;

class Uri
{
    const SSO_PROVIDER_TEST        = 'https://sso.trustap.com/auth/realms/trustap-stage';
    const SSO_PROVIDER_LIVE        = 'https://sso.trustap.com/auth/realms/trustap';
    const PAYMENT_GATEWAY_SETTINGS = 'admin.php?page=wc-settings&tab=checkout&section=trustap';
    const TRUSTAP_TERMS_OF_USE     = 'https://www.trustap.com/terms';

    public static function OIDC_LOGOUT(): string
    {
        return (new \WP_Rewrite())->using_permalinks() ?
            get_rest_url() . 'trustap/v1/oidc-logout?_wpnonce=' :
            get_rest_url() . 'trustap/v1/oidc-logout&_wpnonce=';
    }

    public static function OIDC_TEST_LOGIN(): string
    {
        return get_rest_url() . 'trustap/v1/oidc-test-login';
    }

    public static function OIDC_LIVE_LOGIN(): string
    {
        return get_rest_url() . 'trustap/v1/oidc-live-login';
    }

    public static function API_URL(): string
    {
        return $GLOBALS['testmode'] ?
            'https://dev.stage.trustap.com/api/v1/' :
            'https://dev.trustap.com/api/v1/';
    }

    public static function ACTION_PAGE_URL(): string
    {
        return $GLOBALS['testmode'] ?
            'https://actions.stage.trustap.com/' :
            'https://actions.trustap.com/';
    }
    public static function CONFIRM_HANDOVER_URL(): string
    {
        return get_rest_url() . 'trustap/v1/confirm-handover';
    }
}
