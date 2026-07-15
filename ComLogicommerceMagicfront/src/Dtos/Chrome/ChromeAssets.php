<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Chrome;

use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetAssetsBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use SDK\Core\Dtos\ElementCollection;

/**
 * The rendered output of one chrome region (header or footer): the widget-tree pages plus the
 * template list and compiled CSS/JS the theme needs. A pure value object — it holds no Twig
 * dependency; the caller maps its fields onto Twig globals via {@see \Plugins\ComLogicommerceMagicfront\Enums\ChromeKind}.
 *
 * fromWidgets() is the single place the chrome build lives (flatten → collect types → template
 * list → assets), shared by both sources: the dcsapi editor path and the LC FOB blob path.
 *
 * Runtime-only (uses SDK + FWK-adjacent services); never referenced by the docker PHAR renderer.
 */
final class ChromeAssets {

    /**
     * @param array<string, string> $templateList type => templateHtml
     */
    public function __construct(
        public readonly ?ElementCollection $pages,
        public readonly array $templateList,
        public readonly string $css,
        public readonly string $js,
    ) {}

    /** The absent / empty region — theme falls back to its own header/footer. */
    public static function empty(): self {
        return new self(null, [], '', '');
    }

    /**
     * Build from the region's widget-instance roots (a collection) and the templates that back
     * them (the shared schema, or a dcsapi type→template map). Only templates for types actually
     * present are kept.
     *
     * @param array<string, WidgetTemplate> $templates
     */
    public static function fromWidgets(?ElementCollection $widgets, array $templates): self {
        $widgetRoots = $widgets?->getItems() ?? [];
        if ($widgetRoots === []) {
            return self::empty();
        }

        $pages = WidgetToPageTransformer::transform($widgets);
        $flatWidgets = WidgetTypeCollector::flatten($widgetRoots);

        $templateList = [];
        foreach (WidgetTypeCollector::fromWidgets($flatWidgets) as $type) {
            if (isset($templates[$type])) {
                $templateList[$type] = $templates[$type]->getTemplateHtml();
            }
        }

        $assets = (new WidgetAssetsBuilder())->build($flatWidgets, $templates);

        return new self($pages, $templateList, $assets['css'], $assets['js']);
    }
}
