<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Widgets;

use SDK\Core\Dtos\ElementCollection;

/**
 * Iterable/countable collection of WidgetTemplate. Hydrates its `items` from the raw blob schema
 * arrays via ElementTrait, so the shared chrome schema is a typed object, not a bare array.
 *
 * @method WidgetTemplate[] getItems()
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Widgets
 */
class WidgetTemplateCollection extends ElementCollection {

    protected function setItems(array $items): void {
        $this->items = $this->setArrayField($items, WidgetTemplate::class);
    }

    /** @return array keyed by template id (== widget type) */
    public function byId(): array {
        $byId = [];
        foreach ($this->getItems() as $template) {
            $byId[$template->getId()] = $template;
        }
        return $byId;
    }
}
