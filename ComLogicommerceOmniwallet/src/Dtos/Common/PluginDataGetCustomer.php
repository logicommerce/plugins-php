<?php

namespace Plugins\ComLogicommerceOmniwallet\Dtos\Common;

use SDK\Core\Dtos\PluginData as CorePluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin data class.
 * The API plugin data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetCustomer::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see PluginData
 *
 * @package Plugins\ComLogicommerceOmniwallet\Dtos\Common
 */
class PluginDataGetCustomer extends CorePluginData {
    use ElementTrait;

    protected array $data = [];

    /**
     * Returns the plugin data.
     *
     * @return array
     */
    public function getData(): array {
        return $this->data;
    }
}
