<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\WidgetTwigRenderingTrait;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderContext;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;

/**
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
class GetWidgetHandler extends AbstractCustomizeHandler {

    use CssGeneratorTrait;
    use JsGeneratorTrait;
    use WidgetTwigRenderingTrait {
        buildTwigEnvironment as private buildBaseTwigEnvironment;
    }

    public function supports(string $type): bool {
        return $type === FunctionType::GET_WIDGET;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return 'application/json; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $pageId   = $controller->getRequestParamValue(Parameters::PAGE, true);
        $widgetId = $controller->getRequestParamValue(Parameters::WIDGET_ID, true);
        $language = Language::getInstance()->getLanguage();

        try {
            $service = WidgetsService::getInstance()->disableCache();

            // Fetch the raw instance subtree ONCE: the Page view drives rendering,
            // the flattened instance list drives per-instance CSS.
            $instance     = $service->getPageWidgetInstanceById($pageId, $widgetId, $language);
            $widget       = $instance !== null ? WidgetToPageTransformer::transformSingle($instance) : null;
            $widget       = $this->resolveCatalogRelations($widget);
            $neededTypes  = WidgetTypeCollector::templateKeysFromPages([$widget]);

            $templates          = $service->getWidgetTemplatesForTypes($neededTypes);
            $widgetTemplateList = $this->buildWidgetTemplateList($neededTypes, $templates);
            $html               = $this->renderWidget($controller, $widget, $widgetTemplateList);

            // Per-instance CSS: flatten the instance subtree so every widget's styleValues
            // emit their `[data-widget-id]`-scoped rules — same generator the full page uses.
            $flatWidgets = $instance !== null ? WidgetTypeCollector::flatten([$instance]) : [];
            $css = $this->generateCss($flatWidgets, $templates);
            $js  = $this->generateJs($templates);

            // Wrap in { data: {...} } to match the envelope FWK adds for DTO responses,
            // which the canvas client (widget.ts) unwraps via `root.data`.
            return json_encode(['data' => [
                'success'  => true,
                'widgetId' => $widgetId,
                'type'     => $widget->getCustomType(),
                'html'     => $html,
                'css'      => $css,
                'js'       => $js,
                // Per-widget write revision — the canvas compares it against the
                // DATA_CHANGED's widgetRevision to detect stale renders mid-chain.
                'widgetRevision' => $widget->getWidgetRevision(),
            ]]);
        } catch (\Throwable $e) {
            return json_encode(['data' => [
                'success'      => false,
                'widgetId'     => $widgetId,
                'messageError' => $e->getMessage(),
            ]]);
        }
    }

    /**
     * Hydrate catalog-bound relations (products, categories) on the widget so
     * its templateHtml reads them at render time. The full-page render path
     * already does this in MagicfrontTrait::setMagicfrontData via
     * PageRelationResolver::setData; the /getWidget AJAX path renders one
     * widget in isolation and never went through that flow, which is why
     * category-bound widgets used to render their empty state in the editor
     * sidebar preview even after the merchant picked a category.
     */
    private function resolveCatalogRelations(?PluginPage $widget): ?PluginPage {
        if ($widget === null) {
            return null;
        }
        $collection = new ElementCollection(['items' => [$widget]]);
        $resolved   = PageRelationResolver::setData($collection);
        $items      = $resolved !== null ? $resolved->getItems() : [];
        if (empty($items)) {
            return $widget;
        }
        $first = $items[0];
        return $first instanceof PluginPage ? $first : $widget;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    protected function renderWidget(
        ComLogicommerceMagicfrontController $controller,
        PluginPage $widget,
        array $widgetTemplateList
    ): string {
        $widgetId   = $widget->getDraftId() ?: $widget->getId();
        $widgetType = $widget->getCustomType();
        $lookupKey  = $widget->getTemplateKey();

        $twigEnv = $this->buildTwigEnvironment($controller, $widgetTemplateList);
        $html    = $this->renderWidgetHtml($twigEnv, $lookupKey, $widgetTemplateList, [
            'page'            => $widget,
            'moduleType'      => $widgetType,
            'moduleSettings'  => $widget->getModuleSettings(),
            'widgetId'        => $widgetId,
            'version'         => Theme::getInstance()->getVersion(),
            // The full-page render feeds widgets `shared` as a template local via the widgets macro;
            // this per-widget AJAX path renders the template directly, so expose the same local here
            // (and mff_widget_slot propagates it to slot children through the render context).
            'shared'          => $this->buildSharedForTypes(array_keys($widgetTemplateList)),
        ]);

        return $this->wrapWithMarkers($widgetId, $widgetType, $html);
    }

    /**
     * Extends the base per-widget Twig environment ({@see WidgetTwigRenderingTrait::buildTwigEnvironment},
     * aliased as buildBaseTwigEnvironment) with the `shared` container. The full-page render exposes
     * `shared` (countries/locations/… the FWK globals lack) via the controller's provider dispatch; this
     * per-widget AJAX path (e.g. userPanel tab swap) rebuilds it for the types being rendered so
     * account/address widgets get their form data, not just `session`.
     */
    protected function buildTwigEnvironment(
        ComLogicommerceMagicfrontController $controller,
        array $widgetTemplateList
    ): \Twig\Environment {
        $twigEnv = $this->buildBaseTwigEnvironment($controller, $widgetTemplateList);
        $twigEnv->addGlobal(MagicfrontControllerData::SHARED, $this->buildSharedForTypes(array_keys($widgetTemplateList)));
        return $twigEnv;
    }

    /**
     * Rebuild the `shared` container for a per-widget AJAX render by running the same providers the
     * controller uses on the full page, gated to the widget types present. No session/route needed:
     * the account provider's sharedData draws countries/locations from Application + LMS.
     *
     * @param string[] $presentTypes
     * @return array
     */
    private function buildSharedForTypes(array $presentTypes): array {
        $families = array_values(array_unique(array_map(
            [WidgetTypeCollector::class, 'familyOf'],
            $presentTypes
        )));
        $ctx = new ProviderContext(null, null, $families, static fn(string $key): mixed => null);
        return ProviderRegistry::collectShared(ProviderRegistry::all(), $ctx);
    }

    /**
     * Wrap rendered HTML in MFF_WIDGET_START / MFF_WIDGET_END comment markers
     * so the canvas can detect widget boundaries in the page source.
     */
    private function wrapWithMarkers(string $widgetId, string $widgetType, string $html): string {
        $payload = json_encode(
            ['type' => $widgetType, 'id' => $widgetId, 'draftId' => $widgetId],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        // Escape closing comment sequence to prevent HTML injection
        $payload = str_replace('-->', '--\\u003E', $payload);

        return "<!-- MFF_WIDGET_START {$payload} -->{$html}<!-- MFF_WIDGET_END -->";
    }
}
