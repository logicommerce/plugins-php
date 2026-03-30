<?php

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;
use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
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
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    protected array $properties = [];

    /**
     * Returns the plugin properties.
     *
     * @return array
     */
    public function getProperties(): array {
        return $this->properties;
    }

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PluginPropertiesProperty::class);
    }

    /**
     * Returns the list of route types where MagicFront is enabled.
     *
     * @return array
     */
    public function getAvailablePages(): array {
        foreach ($this->properties as $property) {
            if ($property->getName() === PluginPropertiesPropertyNames::AVAILABLEPAGES) {
                return $property->getValue() ?? [];
            }
        }
        return [];
    }
}
