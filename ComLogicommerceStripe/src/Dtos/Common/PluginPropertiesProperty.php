<?php

namespace Plugins\ComLogicommerceStripe\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceStripe\Enums\PluginPropertiesPropertyNames;

/**
 * This is the plugin property class.
 * The API plugin property data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginPropertiesProperty::getName()
 * @see PluginPropertiesProperty::getValue()
 *
 * @see Element
 * @see ElementTrait
 * @see CorePluginProperties
 *
 * @package Plugins\ComLogicommerceStripe\Dtos\Common
 */
class PluginPropertiesProperty {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected string $value = '';

    /**
     * Returns the plugin property name.
     *
     * @return string
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property value.
     *
     * @return string
     */
    public function getValue(): string {
        return $this->value;
    }
}
