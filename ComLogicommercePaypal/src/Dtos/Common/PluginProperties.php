<?php

namespace Plugins\ComLogicommercePaypal\Dtos\Common;

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
 * @package Plugins\ComLogicommercePaypal\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    protected array $properties = [];

    protected array $connectors = [];

    protected array $eventsResults = [];

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

    /**
     * Returns the plugin property properties properties.
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
     * Returns the plugin triggers.
     *
     * @return array
     */
    public function getEventsResults(): array {
        return $this->eventsResults;
    }

    public function setEventResults(string $trigger, array $results) {
        $this->eventsResults[$trigger] = $results;
    }
}
