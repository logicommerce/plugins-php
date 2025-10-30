<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Common;

/**
 * This is the adapter info class.
 * The API adapter info data will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Common
 * 
 * @see \JsonSerializable
 */
class AdapterInfoDTO implements \JsonSerializable {

    private string $platform = "LogiCommerce";
    private string $adapterName = "Oct8ne official adapter for LogiCommerce";
    private string $adapterVersion = "1.0";
    private string $developedBy = "LogiCommerce";
    private string $supportUrl = "https://www.logicommerce.com";
    private string $apiVersion = "2.4";
    private bool $enabled = true;

    public function __construct() {
    }

    function setPlatform(string $platform) {
        $this->platform = $platform;
    }

    function getPlatform() {
        return $this->platform;
    }

    function setAdapterName(string $adapterName) {
        $this->adapterName = $adapterName;
    }

    function getAdapterName() {
        return $this->adapterName;
    }

    function setAdapterVersion(string $adapterVersion) {
        $this->adapterVersion = $adapterVersion;
    }

    function getAdapterVersion() {
        return $this->adapterVersion;
    }

    function setDevelopedBy(string $developedBy) {
        $this->developedBy = $developedBy;
    }

    function getDevelopedBy() {
        return $this->developedBy;
    }

    function setSupportUrl(string $supportUrl) {
        $this->supportUrl = $supportUrl;
    }

    function getSupportUrl() {
        return $this->supportUrl;
    }

    function setApiVersion(string $apiVersion) {
        $this->apiVersion = $apiVersion;
    }

    function getApiVersion() {
        return $this->apiVersion;
    }

    function setEnabled(bool $enabled) {
        $this->enabled = $enabled;
    }

    function getEnabled() {
        return $this->enabled;
    }

    /**
     * Serialize the adapter info DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'platform' => $this->platform,
            'adapterName' => $this->adapterName,
            'adapterVersion' => $this->adapterVersion,
            'developedBy' => $this->developedBy,
            'supportUrl' => $this->supportUrl,
            'apiVersion' => $this->apiVersion,
            'enabled' => $this->enabled
        ];
    }

}