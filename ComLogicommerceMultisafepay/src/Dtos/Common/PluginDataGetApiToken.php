<?php

namespace Plugins\ComLogicommerceMultisafepay\Dtos\Common;

use SDK\Core\Dtos\PluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the multisafePay token class.
 * The data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetApiToken::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceMultisafepay\Dtos\Common
 */
class PluginDataGetApiToken extends PluginData {
    use ElementTrait;

    protected ?PluginDataGetApiTokenData $data = null;

    protected function setData(array $data){
        $this->data = new PluginDataGetApiTokenData($data);
    }

    /**
     * Returns the plugin payment methods data.
     *
     * @return PluginDataGetApiTokenData
     */
    public function getData(): PluginDataGetApiTokenData {
        return $this->data;
    }
}