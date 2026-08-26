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
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Chrome
 */
class ChromeContent extends Element {
    use ElementTrait;

    protected ?WidgetInstanceCollection $header = null;

    protected ?WidgetInstanceCollection $footer = null;

    protected ?WidgetInstanceCollection $accountPanel = null;

    protected ?WidgetInstanceCollection $basketPanel = null;

    protected ?WidgetInstanceCollection $mobileMenuPanel = null;

    protected function setHeader(array $header): void {
        $this->header = new WidgetInstanceCollection(['items' => $header]);
    }

    protected function setFooter(array $footer): void {
        $this->footer = new WidgetInstanceCollection(['items' => $footer]);
    }

    protected function setAccountPanel(array $accountPanel): void {
        $this->accountPanel = new WidgetInstanceCollection(['items' => $accountPanel]);
    }

    protected function setBasketPanel(array $basketPanel): void {
        $this->basketPanel = new WidgetInstanceCollection(['items' => $basketPanel]);
    }

    protected function setMobileMenuPanel(array $mobileMenuPanel): void {
        $this->mobileMenuPanel = new WidgetInstanceCollection(['items' => $mobileMenuPanel]);
    }

    public function widgetsFor(ChromeKind $kind): ?WidgetInstanceCollection {
        return match ($kind) {
            ChromeKind::Header => $this->header,
            ChromeKind::Footer => $this->footer,
            ChromeKind::AccountPanel => $this->accountPanel,
            ChromeKind::BasketPanel => $this->basketPanel,
            ChromeKind::MobileMenuPanel => $this->mobileMenuPanel,
        };
    }
}
