<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Chrome;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstanceCollection;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplateCollection;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * A published MagicFront chrome blob, hydrated straight from a page's pageContent JSON the way
 * the SDK hydrates API responses: hand the decoded array to the DTO and ElementTrait fills the
 * typed fields (content → ChromeContent, schema → WidgetTemplate[]). Replaces the old manual
 * decode + toWidgetInstances + toTemplates helpers.
 *
 * Works for both chrome sources: the generic `mff_CHROME` page and a page's OWN embedded chrome
 * (content.header / content.footer inside its blob). The shared `schema` must carry the header /
 * footer widget templates (publisher responsibility) — else the built assets are incomplete.
 *
 * Blob shape: { "content": { "header": [...], "footer": [...] }, "schema": [ ... ] }.
 * Runtime-only (SDK DTOs); never referenced by the docker PHAR renderer.
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Chrome
 */
class ChromeDocument extends Element {
    use ElementTrait;

    protected ?ChromeContent $content = null;

    protected ?WidgetTemplateCollection $schema = null;

    protected function setContent(array $content): void {
        $this->content = new ChromeContent($content);
    }

    protected function setSchema(array $schema): void {
        $this->schema = new WidgetTemplateCollection(['items' => $schema]);
    }

    /**
     * Hydrate from a page's pageContent JSON. Returns null when absent or not a MagicFront blob
     * (no `content` object), so the caller falls back to the theme's own header / footer.
     */
    public static function fromJson(?string $blob): ?self {
        if ($blob === null || $blob === '') {
            return null;
        }
        try {
            $data = json_decode($blob, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data) || !isset($data['content']) || !is_array($data['content'])) {
            return null;
        }
        return new self($data);
    }

    /** True when this blob carries a non-empty widget tree for the given kind. */
    public function hasKind(ChromeKind $kind): bool {
        $widgets = $this->widgetsFor($kind);
        return $widgets !== null && count($widgets) > 0;
    }

    public function widgetsFor(ChromeKind $kind): ?WidgetInstanceCollection {
        return $this->content?->widgetsFor($kind);
    }

    /** @return array keyed by template id (== widget type) */
    public function templatesById(): array {
        return $this->schema?->byId() ?? [];
    }
}
