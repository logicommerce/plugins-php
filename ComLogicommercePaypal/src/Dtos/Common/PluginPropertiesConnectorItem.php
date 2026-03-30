<?php

namespace Plugins\ComLogicommercePaypal\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin property connector item class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginPropertiesConnectorItem::getItemId()
 * @see PluginPropertiesConnectorItem::getProperties()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommercePaypal\Dtos\Common
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
