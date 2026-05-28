<?php

namespace Plugins\ComLogicommerceOmniwallet\Services;

use Plugins\ComLogicommerceOmniwallet\Enums\PluginRouteParameters;
use Plugins\ComLogicommerceOmniwallet\Mapper\TokenMapper;
/**
 * This is the class GetTokenService to get the token information
 * 
 * @package Plugins\ComLogicommerceOmniwallet\Services
 * 
 * @see BaseService
 * @see TokenMapper
 */
class GetTokenService extends BaseService {

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /**
     * Process the request
     *
     * @return mixed
     */
    public function process(): mixed {
        return $this->getToken();
    }

    /**
     * Check if the request is cacheable
     *
     * @return bool
     */
    public function isCacheable(): bool {
        return false;
    }

    private function getToken(): object {
        $this->validateLoggedIn();
        $email = $this->getRequestParam(PluginRouteParameters::PARAMETER_EMAIL, "");
        if (empty($email)) {
            $email = $this->getSession()?->getBasket()?->getCustomer()?->getEmail() ?? "";
        }
        $apiToken = $this->getApiToken($email);
        if (empty($apiToken) || $apiToken['token'] === null) {
            $message = $this->getTokenErrorMessage($email);
            return $this->getErrorMapper($message);
        }
        $token = new TokenMapper($apiToken);
        return $token->map();
    }

    private function getTokenErrorMessage(String $email): String {
        if (empty($email)) {
            return "Error getting the token";
        }
        return "Error getting the token for the email: " . $email;
    }
}
