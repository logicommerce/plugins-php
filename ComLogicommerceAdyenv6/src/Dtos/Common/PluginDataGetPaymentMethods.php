<?php

namespace Plugins\ComLogicommerceAdyenv6\Dtos\Common;

use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\PluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin property class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetPaymentMethods::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceAdyenv6\Dtos\Common
 */
class PluginDataGetPaymentMethods extends PluginData {
    use ElementTrait;

    protected ?PluginDataGetPaymentMethodsData $data = null;

    protected function setData(array $data){
        $this->data = new PluginDataGetPaymentMethodsData($data);
    }

    /**
     * Returns the plugin payment methods data.
     *
     * @return PluginDataGetPaymentMethodsData
     */
    public function getData(): PluginDataGetPaymentMethodsData {
        return $this->data;
    }
}