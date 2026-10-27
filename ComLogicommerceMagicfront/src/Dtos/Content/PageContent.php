<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Content;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstanceCollection;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * The `content` object of a published page blob: its widget tree, a typed WidgetInstanceCollection
 * hydrated by ElementTrait. Any other content keys (`languages`) are simply ignored; `pageType` is kept.
 *
 * @see PageDocument
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Content
 */
class PageContent extends Element {
    use ElementTrait;

    protected ?WidgetInstanceCollection $widgets = null;

    /** The family's page type as the backend writes it (`HOME`, `LANDING`, `PRODUCT`, …); '' on older blobs. */
    protected string $pageType = '';

    /** @var string[]|null */
    protected ?array $accountSections = null;

    protected function setWidgets(array $widgets): void {
        $this->widgets = new WidgetInstanceCollection(['items' => $widgets]);
    }

    public function getWidgets(): ?WidgetInstanceCollection {
        return $this->widgets;
    }

    protected function setPageType(string $pageType): void {
        $this->pageType = $pageType;
    }

    public function getPageType(): string {
        return $this->pageType;
    }

    /**
     * `content.accountSections` of a published account page (#4504): the sectionKey of every section it paints, or null
     * when the blob does not carry it (published before the list existed, or not an account page). A value that is
     * not a list reads as absent.
     *
     * @param mixed $accountSections
     */
    protected function setAccountSections($accountSections): void {
        $this->accountSections = is_array($accountSections)
            ? array_values(array_filter($accountSections, 'is_string'))
            : null;
    }

    /** @return string[]|null */
    public function getAccountSections(): ?array {
        return $this->accountSections;
    }
}
