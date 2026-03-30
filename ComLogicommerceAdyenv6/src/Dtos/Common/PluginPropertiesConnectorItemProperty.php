<?php

namespace Plugins\ComLogicommerceAdyenv6\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceAdyenv6\Enums\PluginPropertiesConnectorItemPropertyNames;

/**
 * This is the plugin properties connector values class.
 * The API plugin property connector values data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginPropertiesConnectorItemProperty::getName()
 * @see PluginPropertiesConnectorItemProperty::getValue()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceAdyenv6\Dtos\Common
 */
class PluginPropertiesConnectorItemProperty {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected string $value = '';

    /**
     * Returns the plugin property connectors items property name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesConnectorItemPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property connectors items property value.
     *
     * @return string
     */
    public function getValue(): string {
        return $this->value;
    }
}
