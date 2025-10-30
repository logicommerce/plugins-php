<?php

namespace Plugins\ComLogicommerceBizum\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceBizum\Enums\PluginPropertiesConnectorItemPropertyNames;

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
 * @package Plugins\ComLogicommerceBizum\Dtos\Common
 */
class PluginPropertiesConnectorItemProperty {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected string $value = '';

    /**
     * Returns the plugin property connectors items property name.
     *
     * @return int
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesConnectorItemPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property connectors items property value.
     *
     * @return array
     */
    public function getValue(): string {
        return $this->value;
    }
}
