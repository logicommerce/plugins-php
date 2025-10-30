<?php

namespace Plugins\ComLogicommerceRedsys\Dtos\Common;

use SDK\Core\Dtos\PluginData as CorePluginData;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin data class.
 * The API plugin data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetListRecurringDetails::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginData
 *
 * @package Plugins\ComLogicommerceRedsys\Dtos\Common
 */
class PluginDataGetListRecurringDetails extends CorePluginData {
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
