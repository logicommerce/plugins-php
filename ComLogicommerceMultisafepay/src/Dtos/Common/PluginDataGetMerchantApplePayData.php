<?php

namespace Plugins\ComLogicommerceMultisafepay\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin apple pay session data class.
 *
 * @see PluginDataGetMerchantApplePayData::getApplePaySession()
 * @see PluginDataGetMerchantApplePayData::getSuccess()
 * @see PluginDataGetMerchantApplePayData::getStatus()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceMultisafepay\Dtos\Common
 */
class PluginDataGetMerchantApplePayData {
    use ElementTrait;

    protected string $applePaySession = '';
    protected string $success = '';
    protected int $status = 0;

    /**
     * Returns the applePaySession.
     *
     * @return string
     */
    public function getApplePaySession(): string {
        return $this->applePaySession;
    }

    protected function setApiToken(string $applePaySession){
        $this->applePaySession = $applePaySession;
    }
    
    /**
     * Returns the success.
     *
     * @return string
     */
    public function getSuccess(): string {
        return $this->success;
    }

    protected function setSuccess(string $success){
        $this->success = $success;
    }

    /**
     * Returns the property status.
     *
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }

    protected function setStatus(int $status){
        $this->status = $status;
    }
}