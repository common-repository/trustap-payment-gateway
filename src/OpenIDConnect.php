<?php

namespace Trustap\PaymentGateway;

use Exception;
use Jumbojett\OpenIDConnectClient;
use Trustap\PaymentGateway\Enumerators\Uri as UriEnumerator;
use Trustap\PaymentGateway\Helper\Validator;

class OpenIDConnect
{
    /**
     * @var string
     */
    private $environment;

    public function __construct($environment)
    {
        $this->setEnvironment($environment);
    }

    public function logIn($grants): void
    {
        $provider_url  = $this->getProviderUrl();
        $environment   = $this->getEnvironment();
        $client_id     = get_option("trustap_{$environment}_client_id");
        $client_secret = get_option("trustap_{$environment}_client_secret");
        $redirect_url  = $environment === "test" ?
            UriEnumerator::OIDC_TEST_LOGIN() :
            UriEnumerator::OIDC_LIVE_LOGIN();

        $oidc = new OpenIDConnectClient($provider_url, $client_id, $client_secret);
        $oidc->providerConfigParam(
            array(
                'token_endpoint' => $provider_url . '/protocol/openid-connect/token'
            )
        );
        $oidc->addScope($grants);
        $oidc->setRedirectURL($redirect_url);
        $oidc->authenticate();

        $user = $oidc->getVerifiedClaims();

        update_option(
            "trustap_{$environment}_user_id",
            Validator::sanitize_string($user->sub)
        );
        update_option(
            "trustap_{$environment}_email",
            Validator::email_address($user->email)
        );
        update_option(
            "trustap_{$environment}_id_token",
            Validator::sanitize_string($oidc->getIdToken())
        );
    }

    public function logOut($environment): void
    {
        $provider_url  = get_option("trustap_{$environment}_provider_url");
        $client_id     = get_option("trustap_{$environment}_client_id");
        $client_secret = get_option("trustap_{$environment}_client_secret");
        $id_token      = get_option("trustap_{$environment}_id_token");

        delete_option("trustap_{$environment}_provider_url");
        delete_option("trustap_{$environment}_user_id");
        delete_option("trustap_{$environment}_client_id");
        delete_option("trustap_{$environment}_client_secret");
        delete_option("trustap_{$environment}_email");
        delete_option("trustap_{$environment}_id_token");

        $settings_uri =
            admin_url() .
            UriEnumerator::PAYMENT_GATEWAY_SETTINGS .
            '&successful_logout=true';

        // Make sure to redirect if session ended
        try {
            $oidc = new OpenIDConnectClient(
                $provider_url,
                $client_id,
                $client_secret
            );
            $oidc->signOut($id_token, $settings_uri);
        } catch (Exception $e) {
            if (wp_redirect($settings_uri)) {
                exit;
            }
        }
    }

    private function setEnvironment($environment)
    {
        $this->environment = $environment;
    }

    private function getEnvironment()
    {
        return $this->environment;
    }

    private function getProviderUrl(): string
    {
        $environment = $this->getEnvironment();
        return $environment === 'test' ?
            UriEnumerator::SSO_PROVIDER_TEST :
            UriEnumerator::SSO_PROVIDER_LIVE;
    }
}
