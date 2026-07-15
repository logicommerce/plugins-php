<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Chrome;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstanceCollection;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * The `content` object of a chrome blob: the header and footer widget trees, each a typed
 * WidgetInstanceCollection. Hydrated by ElementTrait exactly like the SDK DTOs — the collection
 * turns the raw arrays into full WidgetInstance trees (children recurse via
 * WidgetInstance::setChildren). Any other content keys (a page's own `widgets` / `languages`)
 * are simply ignored, so this hydrates equally from the generic mff_CHROME blob and a page's own
 * embedded chrome.
 *
 * @see ChromeDocument
 */
class ChromeContent extends Element {
    use ElementTrait;

    protected ?WidgetInstanceCollection $header = null;

    protected ?WidgetInstanceCollection $footer = null;

    protected function setHeader(array $header): void {
        $this->header = new WidgetInstanceCollection(['items' => $header]);
    }

    protected function setFooter(array $footer): void {
        $this->footer = new WidgetInstanceCollection(['items' => $footer]);
    }

    public function widgetsFor(ChromeKind $kind): ?WidgetInstanceCollection {
        return match ($kind) {
            ChromeKind::Header => $this->header,
            ChromeKind::Footer => $this->footer,
        };
    }
}
