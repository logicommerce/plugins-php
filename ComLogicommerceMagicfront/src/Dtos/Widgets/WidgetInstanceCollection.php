<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Widgets;

use SDK\Core\Dtos\ElementCollection;

/**
 * Iterable/countable collection of WidgetInstance roots. Hydrates its `items` from the raw blob
 * arrays via ElementTrait — the same shape a chrome tree has coming out of the MagicFront API —
 * so a chrome region is a typed object, not a bare array.
 *
 * @method WidgetInstance[] getItems()
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Widgets
 */
class WidgetInstanceCollection extends ElementCollection {

    protected function setItems(array $items): void {
        $this->items = $this->setArrayField($items, WidgetInstance::class);
    }
}
