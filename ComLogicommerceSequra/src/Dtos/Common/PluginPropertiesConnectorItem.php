<?php

namespace Plugins\ComLogicommerceSequra\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin property class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginProperties::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceSequra\Dtos\Common
 */
class PluginPropertiesConnectorItem {
    use ElementTrait;

    protected int $itemId = 0;

    protected array $properties = [];

    /**
     * Returns the plugin property connectors itemId.
     *
     * @return int
     */
    public function getItemId(): int {
        return $this->itemId;
    }

    /**
     * Returns the plugin property connectors properties.
     *
     * @return array
     */
    public function getProperties(): array {
        return $this->properties;
    }

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PluginPropertiesConnectorItemProperty::class);
    }
}
