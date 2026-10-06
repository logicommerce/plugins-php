<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Utils;
use SDK\Core\Resources\Timer;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\WidgetTwigRenderingTrait;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderContext;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Resources\ContentLocaleScope;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontPageType;
use Plugins\ComLogicommerceMagicfront\Enums\SampleSituationParam;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Dtos\Catalog\Category;

/**
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
class GetWidgetHandler extends AbstractCustomizeHandler {

    use CssGeneratorTrait;
    use JsGeneratorTrait;
    use WidgetTwigRenderingTrait {
        buildTwigEnvironment as private buildBaseTwigEnvironment;
    }

    /** Per-request memo: `getWidgets` renders N widgets of the same page, which share its facts and its sample. */
    private ?string $pageTypeMemo = null;

    private ?array $sampleMemo = null;
    /** Per-request memo of `GET /samples/category`, shared by the widgets of one `getWidgets` batch. */
    private ?array $categorySampleMemo = null;

    /** @var array `shared` container per sorted family set. */
    private array $sharedMemo = [];

    public function supports(string $type): bool {
        return $type === FunctionType::GET_WIDGET || $type === FunctionType::GET_WIDGETS;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return 'application/json; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $pageId    = $controller->getRequestParamValue(Parameters::PAGE, true);
        // The same content locale the whole-page render uses: the `mff_lang` preview override first (the template
        // gallery previews in English), then the route language.
        $override  = $controller->getRequestParamValue(MagicfrontUtils::MFF_LANG, false);
        $language  = is_string($override) && $override !== '' ? $override : Language::getInstance()->getLanguage();
        // The prices of the render follow that language too: FWK writes them with the session locale, which
        // on the plugin route is the store's default one. In memory, for this response only.
        $scope = ContentLocaleScope::enter(is_string($override) ? $override : null, MagicfrontToken::getToken() !== null);
        try {
            return $this->renderResponse($controller, $pageId, $language);
        } finally {
            $scope->restore();
        }
    }

    private function renderResponse(ComLogicommerceMagicfrontController $controller, string $pageId, string $language): string|false {
        $situation = SampleSituationParam::fromRequest(fn(string $p): mixed => $controller->getRequestParamValue($p, false));
        $service   = WidgetsService::getInstance()->disableCache();
        $rawIds    = (string) ($controller->getRequestParamValue(FunctionType::WIDGET_IDS_PARAM, false) ?? '');
        if ($rawIds !== '') {
            // `getWidgets`: prepare every widget, build ONE Twig environment for all of them and render each one
            // with it. The environment (loader, extensions, plugin bootstrap, shared providers) is ~0.7-3 s of a
            // per-widget render and does not depend on the widget; the HTML itself is tens of ms. A widget that
            // fails does not sink the others; the answer keeps the order asked.
            $ids      = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawIds)))));
            $prepared = [];
            $union    = [];
            foreach ($ids as $widgetId) {
                $prepared[$widgetId] = $this->prepareOne($service, $pageId, $widgetId, $language, $situation);
                $union += $prepared[$widgetId]['list'] ?? [];
            }
            $env     = $union !== [] ? $this->buildTwigEnvironment($controller, $union) : null;
            $widgets = [];
            foreach ($ids as $widgetId) {
                $widgets[] = $this->finishOne($controller, $widgetId, $prepared[$widgetId], $env, $union);
            }
            return json_encode(['data' => ['success' => true, 'widgets' => $widgets]]);
        }
        $widgetId = (string) $controller->getRequestParamValue(Parameters::WIDGET_ID, true);
        $prepared = $this->prepareOne($service, $pageId, $widgetId, $language, $situation);
        return json_encode(['data' => $this->finishOne($controller, $widgetId, $prepared, null, [])]);
    }

    /**
     * Everything a widget's render needs that is specific to it: its instance subtree (the Page view drives the
     * render, the flattened list the per-instance CSS), catalog relations, the sample product and its templates.
     * `['error' => message]` when any of it fails.
     */
    private function prepareOne(WidgetsService $service, string $pageId, string $widgetId, string $language, array $situation): array {
        try {
            $instance = $service->getPageWidgetInstanceById($pageId, $widgetId, $language);
            $widget   = $instance !== null ? WidgetToPageTransformer::transformSingle($instance) : null;
            $widget   = $this->resolveCatalogRelations($widget);
            $this->attachProductSample($service, $pageId, $language, $widget, $situation);
            $this->attachCategorySample($service, $pageId, $language, $widget, $situation);
            $neededTypes = WidgetTypeCollector::templateKeysFromPages([$widget]);
            $templates   = $service->getWidgetTemplatesForTypes($neededTypes);
            return [
                'instance'  => $instance,
                'widget'    => $widget,
                'templates' => $templates,
                'list'      => $this->buildWidgetTemplateList($neededTypes, $templates),
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * One widget's payload `{success, widgetId, type, html, css, js, widgetRevision}` (or `{success: false, widgetId,
     * messageError}`), the same for `getWidget` and each entry of `getWidgets`. `$env` / `$union` are the batch's
     * shared Twig environment and template list; null / empty = build this widget's own (single `getWidget`).
     */
    private function finishOne(ComLogicommerceMagicfrontController $controller, string $widgetId, array $prepared,
            ?\Twig\Environment $env, array $union): array {
        try {
            if (isset($prepared['error'])) {
                throw new \RuntimeException($prepared['error']);
            }
            $widget = $prepared['widget'];
            Utils::addTimerDebugFlag('gw-render', Timer::START_SUFFIX);
            $html   = $this->renderWidget($controller, $widget, $env !== null ? $union : $prepared['list'], $env);
            Utils::addTimerDebugFlag('gw-render', Timer::END_SUFFIX);

            // Per-instance CSS: flatten the instance subtree so every widget's styleValues
            // emit their `[data-widget-id]`-scoped rules — same generator the full page uses.
            $flatWidgets = $prepared['instance'] !== null ? WidgetTypeCollector::flatten([$prepared['instance']]) : [];
            $css = $this->generateCss($flatWidgets, $prepared['templates']);
            $js  = $this->generateJs($prepared['templates']);

            // The caller wraps it in { data: {...} } to match the envelope FWK adds for DTO responses,
            // which the canvas client (widget.ts) unwraps via `root.data`.
            return [
                'success'  => true,
                'widgetId' => $widgetId,
                'type'     => $widget->getCustomType(),
                'html'     => $html,
                'css'      => $css,
                'js'       => $js,
                // Per-widget write revision — the canvas compares it against the
                // DATA_CHANGED's widgetRevision to detect stale renders mid-chain.
                'widgetRevision' => $widget->getWidgetRevision(),
            ];
        } catch (\Throwable $e) {
            return [
                'success'      => false,
                'widgetId'     => $widgetId,
                'messageError' => $e->getMessage(),
            ];
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

    /**
     * The per-widget refresh is an editor-only path with no route product, so a product-detail widget
     * (`page.product`) re-rendered after an edit would paint EMPTY and vanish from the canvas. Same rule
     * as the full editor render (MagicfrontTrait::routeProduct): on a PRODUCT page the widget gets
     * MagicFront's sample product and the custom-tag names, attached recursively into its subpages.
     */
    private function attachProductSample(WidgetsService $service, string $pageId, string $language, ?PluginPage $widget, array $situation = []): void {
        $this->pageTypeMemo ??= $service->getPageFacts($pageId)->getPageType();
        if ($widget === null || $this->pageTypeMemo !== MagicfrontPageType::PRODUCT) {
            return;
        }
        $this->sampleMemo ??= $service->getSample(MagicfrontPageType::SAMPLE_KIND[MagicfrontPageType::PRODUCT], $language, $situation);
        $sample = $this->sampleMemo;
        if (!is_array($sample['product'] ?? null)) {
            return;
        }
        $collection = new ElementCollection(['items' => [$widget]]);
        PageRelationResolver::attachProduct($collection, new Product($sample['product']));
        PageRelationResolver::attachComments($collection, PageRelationResolver::sampleComments($sample));
        PageRelationResolver::attachProductCustomTags($collection, PageRelationResolver::sampleCustomTags($sample));
        PageRelationResolver::attachProductRelatedGroups($collection, PageRelationResolver::sampleRelatedGroups($sample));
        PageRelationResolver::attachBreadcrumb($collection, PageRelationResolver::sampleTrail($sample));
    }

    /**
     * Same rule as {@see attachProductSample()} for a CATEGORY page: the full editor render paints MagicFront's sample
     * category (MagicfrontTrait::routeCategory / routeProducts), so a listing widget refreshed alone gets the same
     * `page.category`, `page.categories` and `page.products` — without them productList@2 and the category primitives
     * repainted EMPTY after every edit.
     */
    private function attachCategorySample(WidgetsService $service, string $pageId, string $language, ?PluginPage $widget, array $situation = []): void {
        $this->pageTypeMemo ??= $service->getPageFacts($pageId)->getPageType();
        if ($widget === null || $this->pageTypeMemo !== MagicfrontPageType::CATEGORY) {
            return;
        }
        $this->categorySampleMemo ??= $service->getSample(MagicfrontPageType::SAMPLE_KIND[MagicfrontPageType::CATEGORY], $language, $situation);
        $sample = $this->categorySampleMemo;
        if (!is_array($sample['category'] ?? null)) {
            return;
        }
        $collection = new ElementCollection(['items' => [$widget]]);
        PageRelationResolver::attachCategory($collection, new Category($sample['category']),
            PageRelationResolver::sampleCollection($sample, 'subcategories', Category::class));
        PageRelationResolver::attachProducts($collection, PageRelationResolver::sampleCollection($sample, 'products', Product::class));
        PageRelationResolver::attachBreadcrumb($collection, PageRelationResolver::sampleTrail($sample));
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    /** The whole-page macro's default permission map (widgets.html.twig). */
    private const DEFAULT_PERMISSION = [
        'allowMove' => true, 'allowDelete' => true, 'allowDuplicate' => true,
        'allowEdit' => true, 'allowAdd' => true, 'allowAdjacent' => true,
    ];

    protected function renderWidget(
        ComLogicommerceMagicfrontController $controller,
        PluginPage $widget,
        array $widgetTemplateList,
        ?\Twig\Environment $twigEnv = null
    ): string {
        // Same id precedence as the whole-page macro (widgets.html.twig: page.id == 0 ? draftId : id), so a widget
        // refreshed alone keeps the data-widget-id the page gave it.
        $pageId     = $widget->getId();
        $widgetId   = (empty($pageId) || (string) $pageId === '0') ? $widget->getDraftId() : (string) $pageId;
        $widgetType = $widget->getCustomType();
        $lookupKey  = $widget->getTemplateKey();

        $twigEnv ??= $this->buildTwigEnvironment($controller, $widgetTemplateList);
        $shared = $this->buildSharedForTypes(array_keys($widgetTemplateList));
        $html    = $this->renderWidgetHtml($twigEnv, $lookupKey, $widgetTemplateList, [
            'page'            => $widget,
            'moduleType'      => $widgetType,
            'moduleSettings'  => $widget->getModuleSettings(),
            'widgetId'        => $widgetId,
            'version'         => Theme::getInstance()->getVersion(),
            // The full-page render feeds widgets `shared` as a template local via the widgets macro;
            // this per-widget AJAX path renders the template directly, so expose the same local here
            // (and mff_widget_slot propagates it to slot children through the render context).
            'shared'          => $shared,
            // The same locals the whole-page macro passes (userPanel@1 reads `permission`): the defaults there,
            // since a widget refreshed alone has no page-level permission override, and no repeated-card index.
            'permission'      => self::DEFAULT_PERMISSION,
            'repeatIndex'     => null,
        ]);

        return $this->wrapWithMarkers($widgetId, $widgetType, $html, $widget->getDraftId(), $widget->getSlotId());
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
        sort($families);
        // Memoized per request and per family set: the providers are the expensive part of a per-widget render
        // (countries, locations… — in a local shop that is a round of geolocation calls), and a render asked for
        // them TWICE (Twig global + template local); `getWidgets` would ask once per widget.
        $key = implode(',', $families);
        if (!array_key_exists($key, $this->sharedMemo)) {
            $ctx = new ProviderContext(null, null, $families, static fn(string $key): mixed => null);
            $this->sharedMemo[$key] = ProviderRegistry::collectShared(ProviderRegistry::all(), $ctx);
        }
        return $this->sharedMemo[$key];
    }

    /**
     * Wrap rendered HTML in MFF_WIDGET_START / MFF_WIDGET_END comment markers
     * so the canvas can detect widget boundaries in the page source.
     */
    private function wrapWithMarkers(string $widgetId, string $widgetType, string $html, string $draftId = '', ?string $slotId = null): string {
        // The same payload the whole-page macro writes (type, id, draftId, parentId, label, slotId).
        $payload = json_encode(
            ['type' => $widgetType, 'id' => $draftId !== '' ? $draftId : $widgetId, 'draftId' => $draftId !== '' ? $draftId : $widgetId,
             'parentId' => null, 'label' => null, 'slotId' => $slotId !== '' ? $slotId : null],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        // Escape closing comment sequence to prevent HTML injection
        $payload = str_replace('-->', '--\\u003E', $payload);

        return "<!-- MFF_WIDGET_START {$payload} -->{$html}<!-- MFF_WIDGET_END -->";
    }
}
