<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Utils;
use SDK\Core\Resources\Timer;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Enums\PreviewPageParam;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\WidgetTwigRenderingTrait;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Providers\SharedNeeds;
use Plugins\ComLogicommerceMagicfront\Core\Resources\ContentLocaleScope;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\AccountSample;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
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

    /** Per-request memo of `GET /samples/account`, shared by the widgets of one `getWidgets` batch. */
    private ?array $accountSampleMemo = null;

    /**
     * #1545: every node of the page, by id, read ONCE for a `getWidgets` batch (null = not read: single `getWidget`,
     * or the read failed). A batch of the product page asked the API one widget at a time — ~30 calls in a row, the
     * 15-20 s the merchant waited after switching «Agotado» / «Disponible» in the simulator.
     *
     * @var array<string, \Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance>|null
     */
    private ?array $instanceIndex = null;

    /**
     * #1545: templates already read in this request, by type key (`family@N`): a batch asks each type once, not once
     * per widget that uses it.
     *
     * @var array<string, mixed>
     */
    private array $templateMemo = [];

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
        // #465: `mfPage` first; the old `page` while editors still send it. One of the two names the page.
        $pageId    = PreviewPageParam::resolve(
            $controller->getRequestParamValue(PreviewPageParam::NAME, false),
            $controller->getRequestParamValue(Parameters::PAGE, false)
        );
        if ($pageId === null) {
            // Neither names a page: the same «required parameter» answer as before the rename.
            $pageId = (string) $controller->getRequestParamValue(Parameters::PAGE, true);
        }
        // The same content locale the whole-page render uses: the `mff_lang` preview override first (the template
        // gallery previews in English), then the route language. Read with the same filter as the whole page
        // (lower case, two letters): `mff_lang=EN` was `en` there and `EN` here.
        $override  = MagicfrontUtils::requestedLanguage();
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
            $this->instanceIndex = $this->indexPageInstances($service, $pageId, $language);
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
            // #1545: from the page read once when the batch has it; a widget it does not hold (an id from
            // somewhere else) is read by id, as before.
            $instance = $this->instanceIndex[$widgetId] ?? $service->getPageWidgetInstanceById($pageId, $widgetId, $language);
            $widget   = $instance !== null ? WidgetToPageTransformer::transformSingle($instance) : null;
            $widget   = $this->resolveCatalogRelations($widget);
            $this->attachProductSample($service, $pageId, $language, $widget, $situation);
            $this->attachCategorySample($service, $pageId, $language, $widget, $situation);
            $this->attachAccountSample($service, $pageId, $language, $widget, $situation);
            $neededTypes = WidgetTypeCollector::templateKeysFromPages([$widget]);
            $templates   = $this->templatesFor($service, $neededTypes);
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
     * #1545: every node of the page (the same read the whole-page render makes, Studio documents included), indexed
     * by id with its subtree. Null when the read fails: the batch then reads each widget by id, as before.
     *
     * @return array<string, \Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance>|null
     */
    private function indexPageInstances(WidgetsService $service, string $pageId, string $language): ?array {
        try {
            $index = [];
            $stack = $service->getPageWidgetInstances($pageId, $language);
            while ($stack !== []) {
                $node = array_pop($stack);
                if (!is_object($node) || !method_exists($node, 'getId')) {
                    continue;
                }
                $index[$node->getId()] ??= $node;
                foreach ($node->getChildren() as $child) {
                    $stack[] = $child;
                }
            }
            return $index;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * #1545: the templates of `$types`, reading from the API only the ones this request has not read yet.
     *
     * @param string[] $types
     * @return array<string, mixed> indexed by type, like {@see WidgetsService::getWidgetTemplatesForTypes()}
     */
    private function templatesFor(WidgetsService $service, array $types): array {
        $missing = array_values(array_diff(array_unique($types), array_keys($this->templateMemo)));
        if ($missing !== []) {
            $this->templateMemo += $service->getWidgetTemplatesForTypes($missing);
            foreach ($missing as $type) {
                $this->templateMemo[$type] ??= null;   // not found: not asked again in this request
            }
        }
        $out = [];
        foreach (array_unique($types) as $type) {
            if (($this->templateMemo[$type] ?? null) !== null) {
                $out[$type] = $this->templateMemo[$type];
            }
        }
        return $out;
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

    /**
     * Same rule as the full editor render (MagicfrontTrait::attachAccountSample) for an account piece refreshed alone:
     * on the account page, in the canvas, it gets the sample customer, so it does not fall back to its own example
     * (or to the empty text) after every edit. Never outside the canvas: the store's section load
     * ({@see WidgetContentHandler}) shares this path and fetches the real account.
     */
    private function attachAccountSample(WidgetsService $service, string $pageId, string $language, ?PluginPage $widget, array $situation = []): void {
        if ($widget === null || !RenderMode::isCanvasMode()) {
            return;
        }
        $this->pageTypeMemo ??= $service->getPageFacts($pageId)->getPageType();
        if ($this->pageTypeMemo !== MagicfrontPageType::USER_AREA) {
            return;
        }
        $this->accountSampleMemo ??= $service->getSample(MagicfrontPageType::SAMPLE_KIND[MagicfrontPageType::USER_AREA], $language, $situation);
        AccountSample::attach(new ElementCollection(['items' => [$widget]]), $this->accountSampleMemo);
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
        $shared = ProviderRegistry::sharedForTypes(array_keys($widgetTemplateList), SharedNeeds::of($widgetTemplateList));
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
        $twigEnv->addGlobal(
            MagicfrontControllerData::SHARED,
            ProviderRegistry::sharedForTypes(array_keys($widgetTemplateList), SharedNeeds::of($widgetTemplateList))
        );
        return $twigEnv;
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
