<?php

namespace Plugins\ComLogicommerceGooglecaptcha\Core\Resources;

use SDK\Core\Dtos\PluginProperties;
use SDK\Core\Enums\MethodType;
use SDK\Core\Interfaces\PluginCaptchaTokenValidator;
use SDK\Core\Resources\Connection;
use SDK\Core\Resources\HttpRequest;


class TokenValidator implements PluginCaptchaTokenValidator {

    private static string $apiKey = '';

    private static string $botScore = '0.5';

    /**
     * 
     */
    public static function verifyToken(string $token, ?PluginProperties $pluginProperties = null, string $action = ''): bool {

        foreach ($pluginProperties->getProperties() as $property) {
            if (property_exists(self::class, $property->getName())) {
                self::${$property->getName()} = $property->getValue();
            }
        }

        $httpRequest = new HttpRequest('https://www.google.com/recaptcha/api/siteverify', MethodType::POST);
        $httpRequest->setData(
            [
                'secret' => self::$apiKey,
                'response' => $token,
                'remoteip' => Connection::getIp()
            ]
        );
        $apiResponse = json_decode($httpRequest->send()['result'], true);

        if ($apiResponse['success'] && $apiResponse['action'] === $action && $apiResponse['score'] >= floatval(self::$botScore)) {
            return true;
        } else {
            return false;
        }
    }
}
