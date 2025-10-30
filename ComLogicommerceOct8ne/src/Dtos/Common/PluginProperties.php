<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Common;

use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin property class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginProperties::getProperties()
 * @see PluginProperties::getConnectors()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    protected array $properties = [];

    protected array $connectors = [];

    /**
     * Returns the plugin connectors.
     *
     * @return array
     */
    public function getConnectors(): array {
        return $this->connectors;
    }

    /**
     * Returns the plugin properties.
     *
     * @return array
     */
    public function getProperties(): array {
        return $this->properties;
    }

    public function setProperties(array $properties) {
        $this->properties = $this->setArrayField($properties, PluginPropertiesProperty::class);
    }
}
