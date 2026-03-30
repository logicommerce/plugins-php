<?php

namespace Plugins\ComLogicommercePaypal\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use SDK\Enums\PluginConnectorType;

/**
 * This is the plugin property connector class.
 * The API plugin property connector data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginPropertiesConnector::getType()
 * @see PluginPropertiesConnector::getItems()
 *
 * @see Element
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommercePaypal\Dtos\Common
 */
class PluginPropertiesConnector {
    use ElementTrait, EnumResolverTrait;

    protected string $type = '';

    protected array $items = [];

    /**
     * Returns the plugin property type.
     *
     * @return string
     */
    public function getType(): string {
        return $this->getEnum(PluginConnectorType::class, $this->type, PluginConnectorType::NONE);
    }

    /**
     * Returns the plugin property items.
     *
     * @return array
     */
    public function getItems(): array {
        return $this->items;
    }

    protected function setItems(array $items): void {
        $this->items = $this->setArrayField($items, PluginPropertiesConnectorItem::class);
    }
}
