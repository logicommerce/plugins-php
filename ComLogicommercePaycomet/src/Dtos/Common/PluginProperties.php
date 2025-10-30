<?php

namespace Plugins\ComLogicommercePaycomet\Dtos\Common;

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
 * @package Plugins\ComLogicommercePaycomet\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    protected array $properties = [];

    protected array $connectors = [];

    /**
     * Returns the plugin properties.
     *
     * @return array
     */
    public function getProperties(): array {
        return $this->properties;
    }

    /**
     * Returns the plugin connectors.
     *
     * @return array
     */
    public function getConnectors(): array {
        return $this->connectors;
    }

    public function setConnectors(array $connectors) {
        $this->connectors = $this->setArrayField($connectors, PluginPropertiesConnector::class);
    }
}
