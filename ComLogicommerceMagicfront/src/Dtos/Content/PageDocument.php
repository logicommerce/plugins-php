<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Content;

use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstanceCollection;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplateCollection;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * A published MagicFront PAGE blob (a page's own content), hydrated straight from its pageContent
 * JSON the way the SDK hydrates API responses — the content counterpart of {@see ChromeDocument}.
 * Replaces the manual PublishedPageContent decode/toWidgetInstances/toPages/toTemplates helpers.
 *
 * The storefront render path is reused verbatim; only WHERE the data comes from changes. The editor
 * fetches widgets/templates from dcsapi; production reads them from the blob inside the already
 * loaded page, so it makes ZERO requests to dcsapi (customers are unauthenticated for that API).
 *
 * Blob shape: { "content": { "languages": [...], "widgets": [ &lt;tree&gt; ] }, "schema": [ ... ] }.
 * Runtime-only (SDK DTOs); never referenced by the docker PHAR renderer.
 */
class PageDocument extends Element {
    use ElementTrait;

    protected ?PageContent $content = null;

    protected ?WidgetTemplateCollection $schema = null;

    protected function setContent(array $content): void {
        $this->content = new PageContent($content);
    }

    protected function setSchema(array $schema): void {
        $this->schema = new WidgetTemplateCollection(['items' => $schema]);
    }

    /**
     * Hydrate from a page's pageContent JSON. Returns null when absent or not a MagicFront page
     * (no content.widgets), so a normal LogiCommerce page renders untouched.
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
        if (!is_array($data) || !isset($data['content']['widgets']) || !is_array($data['content']['widgets'])) {
            return null;
        }
        return new self($data);
    }

    public function widgets(): ?WidgetInstanceCollection {
        return $this->content?->getWidgets();
    }

    /** The widget tree transformed to Page DTOs for PageRelationResolver. */
    public function toPages(): ?ElementCollection {
        return WidgetToPageTransformer::transform($this->widgets());
    }

    /** @return array<string, WidgetTemplate> the full schema keyed by template id (== widget type), unfiltered. */
    public function templatesById(): array {
        return $this->schema?->byId() ?? [];
    }
}
