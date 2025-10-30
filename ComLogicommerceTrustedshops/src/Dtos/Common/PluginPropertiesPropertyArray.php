<?php

namespace Plugins\ComLogicommerceTrustedshops\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceTrustedshops\Enums\PluginPropertiesPropertyNames;

/**
 * This is the plugin property class for array values.
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
class PluginPropertiesPropertyArray {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected array $value = [];

    /**
     * Returns the plugin property name.
     *
     * @return int
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property value in array.
     *
     * @return array
     */
    public function getValue(): array {
        return $this->value;
    }
}
