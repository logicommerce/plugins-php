<?php

namespace Plugins\ComLogicommerceSprinque\Dtos\Common;

use SDK\Core\Dtos\PluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin property class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetBuyerDetails::getData()
 *
 * @see Element
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceSprinque\Dtos\Common
 */
class PluginDataGetBuyerDetails extends PluginData {
    use ElementTrait;

    protected ?PluginDataGetBuyerDetailsData $data = null;

    protected function setData(array $data) {
        $this->data = new PluginDataGetBuyerDetailsData($data);
    }

    /**
     * Returns the plugin payment methods data.
     *
     * @return PluginDataGetBuyerDetailsData
     */
    public function getData(): PluginDataGetBuyerDetailsData {
        return $this->data;
    }
}