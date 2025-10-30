<?php

namespace Plugins\ComLogicommerceMultisafepay\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the multisafepay token data class.
 *
 * @see PluginDataGetApiTokenData::getApiToken()
 * @see PluginDataGetApiTokenData::getSuccess()
 * @see PluginDataGetApiTokenData::getStatus()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceMultisafepay\Dtos\Common
 */
class PluginDataGetApiTokenData {
    use ElementTrait;

    protected string $apiToken = '';
    protected string $success = '';
    protected int $status = 0;

    /**
     * Returns the apiToken.
     *
     * @return string
     */
    public function getApiToken(): string {
        return $this->apiToken;
    }

    protected function setApiToken(string $apiToken){
        $this->apiToken = $apiToken;
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