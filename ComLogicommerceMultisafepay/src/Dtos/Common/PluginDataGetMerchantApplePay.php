<?php

namespace Plugins\ComLogicommerceMultisafepay\Dtos\Common;

use SDK\Core\Dtos\PluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the multisafepay applePay session class.
 * The data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetApiToken::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see PluginData
 *
 * @package Plugins\ComLogicommerceMultisafepay\Dtos\Common
 */
class PluginDataGetMerchantApplePay extends PluginData {
    use ElementTrait;

    protected ?PluginDataGetMerchantApplePayData $data = null;

    protected function setData(array $data){
        $this->data = new PluginDataGetMerchantApplePayData($data);
    }

    /**
     * Returns the plugin payment methods data.
     *
     * @return PluginDataGetMerchantApplePayData
     */
    public function getData(): PluginDataGetMerchantApplePayData {
        return $this->data;
    }
}