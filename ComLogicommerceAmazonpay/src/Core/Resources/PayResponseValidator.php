<?php

namespace Plugins\ComLogicommerceAmazonpay\Core\Resources;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\RoutePaths;
use FWK\Enums\RouteType;
use FWK\Enums\Services;
use SDK\Core\Enums\MethodType;
use SDK\Core\Interfaces\PluginPayResponseValidator;
use SDK\Dtos\PayResponse;

class PayResponseValidator implements PluginPayResponseValidator {

    /**
     * Verify the pay response
     * 
     * @param int $orderId
     * @param PayResponse $payResponse
     * 
     * @return PayResponse
     */
    public static function verifyPayResponse(int $orderId, PayResponse $payResponse, string $validationDataKey): PayResponse {

        return $payResponse;
    }
}
