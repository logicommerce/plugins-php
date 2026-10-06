<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Content;

use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * What the editor path reads from the page record (`GET /pages/{pageId}`): the `chrome` doc-id refs
 * `{header:<id>, footer:<id>}` (empty kinds omitted), the page type and, for a Studio document, its placement.
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Content
 */
class PageFacts extends Element {
    use ElementTrait;

    protected array $chrome = [];

    protected string $pageType = '';

    protected string $studioPlacement = '';

    protected function setChrome(mixed $chrome): void {
        if (!is_array($chrome)) {
            return;
        }
        foreach ([ChromeKind::Header->value, ChromeKind::Footer->value] as $kind) {
            if (isset($chrome[$kind]) && is_string($chrome[$kind]) && $chrome[$kind] !== '') {
                $this->chrome[$kind] = $chrome[$kind];
            }
        }
    }

    protected function setPageType(mixed $pageType): void {
        $this->pageType = is_string($pageType) ? $pageType : '';
    }

    protected function setStudioPlacement(mixed $studioPlacement): void {
        $this->studioPlacement = is_string($studioPlacement) ? $studioPlacement : '';
    }

    public function getChrome(): array {
        return $this->chrome;
    }

    public function getPageType(): string {
        return $this->pageType;
    }

    public function getStudioPlacement(): string {
        return $this->studioPlacement;
    }
}
