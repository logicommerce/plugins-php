<?php

namespace Plugins\ComLogicommerceTrustedshops\Dtos\Common;

use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\Traits\ElementTrait;
use FWK\Enums\Parameters;

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
 * @package Plugins\ComLogicommerceTrustedshops\Dtos\Common
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
        $itemsDTOs = [];
        foreach ($properties as $item) {
            if (is_array($item[Parameters::VALUE])) {
                $itemsDTOs[] = $this->getFieldItem($item, PluginPropertiesPropertyArray::class);
            } else {
                $itemsDTOs[] = $this->getFieldItem($item, PluginPropertiesProperty::class);
            }
        }
        $this->properties = $itemsDTOs;
    }
}