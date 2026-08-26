<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Content;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstanceCollection;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * The `content` object of a published page blob: its widget tree, a typed WidgetInstanceCollection
 * hydrated by ElementTrait. Any other content keys (`languages`) are simply ignored.
 *
 * @see PageDocument
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Content
 */
class PageContent extends Element {
    use ElementTrait;

    protected ?WidgetInstanceCollection $widgets = null;

    protected function setWidgets(array $widgets): void {
        $this->widgets = new WidgetInstanceCollection(['items' => $widgets]);
    }

    public function getWidgets(): ?WidgetInstanceCollection {
        return $this->widgets;
    }
}
