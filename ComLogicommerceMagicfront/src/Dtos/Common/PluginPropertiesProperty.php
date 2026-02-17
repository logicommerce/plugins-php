<?php

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;

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
class PluginPropertiesProperty {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected string|array $value = '';

    /**
     * Returns the plugin property name.
     *
     * @return int
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property value.
     *
     * @return array
     */
    public function getValue(): string|array {
        return $this->value;
    }

    public function setValue(string|array $value): void {
        $this->value = $value;
    }
}
