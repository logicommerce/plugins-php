<?php

namespace Plugins\ComLogicommercePaypal\Core\Resources;

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

        $responseMessage = json_decode($payResponse->getMessage(), true);
        if (isset($responseMessage['requiresConfirmation']) && $responseMessage['requiresConfirmation'] == false) {
            $params = [
                ['name' => 'orderId', 'value' => $orderId],
                ['name' => 'transactionId', 'value' => $payResponse->getTransactionId()]
            ];
            $data = [
                'url' => RoutePaths::getPath(RouteType::CHECKOUT_CONFIRM_ORDER),
                'method' => MethodType::POST,
                'params' => array_merge($params, $payResponse->getData()['params'])
            ];
            $payResponse = new PayResponse(
                array_replace_recursive(
                    $payResponse->toArray(),
                    ['data' => $data]
                )
            );
        }

        if (empty($payResponse->getMessage()) || isset($responseMessage['requiresConfirmation']) && $responseMessage['requiresConfirmation'] == true && empty($responseMessage['url'])) {
            $payResponse = new PayResponse([
                'error' => [
                    'code' => "A01000-PAYPAL_PAY_RESPONSE_ERROR",
                    'status' => '500',
                    'message' => 'Payment processing failed on PayPal',
                ]
            ]);
        }

        return $payResponse;
    }
}
