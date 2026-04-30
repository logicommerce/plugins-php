<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;

/**
 * This is the PluginPropertiesProperty class.
 * The API plugin property entry will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginPropertiesProperty::getName()
 * @see PluginPropertiesProperty::getValue()
 *
 * @see ElementTrait
 * @see EnumResolverTrait
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class PluginPropertiesProperty {
    use ElementTrait, EnumResolverTrait;

    protected string $name = '';

    protected string|array $value = '';

    /**
     * Returns the plugin property name (resolved to a PluginPropertiesPropertyNames enum value).
     *
     * @return string
     */
    public function getName(): string {
        return $this->getEnum(PluginPropertiesPropertyNames::class, $this->name, '');
    }

    /**
     * Returns the plugin property value.
     *
     * @return string|array
     */
    public function getValue(): string|array {
        return $this->value;
    }
}
