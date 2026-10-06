<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\Controllers\Controller;
use FWK\Core\FilterInput\FilterInput;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\Utils;
use SDK\Core\Resources\Timer;
use FWK\Enums\Parameters;
use FWK\Enums\RouteType;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PanelUrl;
use Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BreadcrumbResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetAssetsBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontPageType;
use Plugins\ComLogicommerceMagicfront\Enums\SampleSituationParam;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Services\Parameters\Groups\RelatedItemsParametersGroup;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Resources\Environment;
use SDK\Dtos\Catalog\Category;
use SDK\Dtos\Catalog\Page\Page;
use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Dtos\Common\Route;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataProvider;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderContext;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;

/**
 * Mixes MagicFront-aware batch/data hooks into HTML controllers (Home,
 * Page\Page). Two paths, dispatched by RenderMode::isPreviewMode() — true iff the request
 * is the dcseditor canvas iframe (Sec-Fetch-Dest: iframe) OR carries a
 * non-empty mfToken in the query:
 *
 *   Editor path     — widgets + templates come from dcsapi via WidgetsService.
 *
 *   Storefront path — regular customer page view. Reads the published blob
 *                     from controllerItem's pageContent (the LC FOB page
 *                     already loaded by FWK). ZERO dcsapi calls — customers
 *                     are unauthenticated for that API.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait MagicfrontTrait {

    public const MFF_PREVIEW = 'mff_preview';

    /** Optional content-language override for preview (storefront-contract: mff_lang). Forces the MFF widget content locale, e.g. the template gallery previews in English. */
    public const MFF_LANG = MagicfrontUtils::MFF_LANG;

    /**
     * The Studio DOCUMENT to paint instead of a page: `store:<slug>@N` or a draft `draft:<id>`, one store widget
     * alone. The backend serves it through the same page reads (`/pages/{ref}/widgets`, `/pages/{ref}`) and only
     * to a preview token whose `storeWidget` claim is exactly this ref, so the query value alone opens nothing.
     */
    public const MFF_STORE_WIDGET = 'mff_store_widget';

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $pageId = null;

    /**
     * Flat list of WidgetInstance for the current page. Populated by either
     * loadStorefrontData (from the blob) or setMagicfrontBatchData (from
     * dcsapi). Consumed by emitMagicfrontData to build inline CSS/JS.
     *
     * @var WidgetInstance[]
     */
    protected array $widgets = [];

    /**
     * The page's chrome doc-id refs `{header:<id>, footer:<id>}`. Populated in the editor
     * path from the page record; consumed by emitMagicfrontData → controllerData so
     * TwigInitializer can fetch each chrome doc by id (empty kinds fall back to defaults).
     *
     * @var array
     */
    protected array $pageChrome = [];

    /**
     * The MagicFront page type (`HOME`, `PRODUCT`, …) of the page being painted. Editor path: from the
     * page record; storefront path: from the published blob. Empty until one of them ran. Decides
     * whether a page without a route product gets the sample (`PRODUCT`) or nothing.
     */
    protected string $pageType = '';

    /** True when {@see self::MFF_STORE_WIDGET} named a Studio document: the page id IS the store widget ref. */
    protected bool $studioDocument = false;

    /** Placement of the Studio document's widget (BLOCK | SECTION); empty outside the Studio. */
    protected string $studioPlacement = '';

    /** Memoized `GET /samples/product` (array as the backend returns it); null until first asked. */
    private ?array $productSampleCache = null;

    /** Memoized `GET /samples/category` (array as the backend returns it); null until first asked. */
    private ?array $categorySampleCache = null;

    private array $relatedItemsCache = [];

    private bool $magicfrontPageResolved = false;

    private ?Page $magicfrontPageCache = null;

    // ─── FWK lifecycle ─────────────────────────────────────────────────────

    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            MagicfrontToken::MF_TOKEN => new FilterInput($noMod),
            Parameters::PAGE         => new FilterInput($noMod),
            self::MFF_PREVIEW        => new FilterInput($noMod),
            self::MFF_LANG           => new FilterInput($noMod),
            self::MFF_STORE_WIDGET   => new FilterInput($noMod),
            PanelUrl::PARAM          => new FilterInput($noMod),
            // Raw request `id` (e.g. the selected shopping list) exposed to widgets via requestParams —
            // request state, not API data; widgets apply their own selection rule from it.
            Parameters::ID           => new FilterInput($noMod),
            // The editor's product simulator: the situation the SAMPLE product is painted in.
            SampleSituationParam::STOCK   => new FilterInput($noMod),
            SampleSituationParam::OFFER   => new FilterInput($noMod),
            SampleSituationParam::REVIEWS => new FilterInput($noMod),
            SampleSituationParam::LISTING => new FilterInput($noMod),
            SampleSituationParam::WISHLIST => new FilterInput($noMod),
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;

        $rawToken = $this->getRequestParam(MagicfrontToken::MF_TOKEN, false, null);
        if (!RenderMode::isPreviewMode()) {
            return;
        }
        if (MagicfrontUtils::isIframeRequest()) {
            MagicfrontToken::setToken($rawToken);
        }
        $this->redirectToContentLanguage($route);
        $this->widgetsService = WidgetsService::getInstance();
        $this->pageId = $this->getRequestParam(Parameters::PAGE, false, null);
        $storeWidget = $this->getRequestParam(self::MFF_STORE_WIDGET, false, null);
        if (is_string($storeWidget) && MagicfrontUtils::isStudioDocumentRef($storeWidget)) {
            $this->pageId         = $storeWidget;
            $this->studioDocument = true;
        }
    }

    /**
     * The editor's language bar asks for a language with `mff_lang`; send the preview to this page's address in
     * that language, so the store around the widgets (theme texts, menu, `<html lang>`) is in it too
     * ({@see MagicfrontUtils::languageRedirectUrl}). Only page navigations (GET, not the canvas' own fetches nor a
     * content-only partial): those already run on the right route once the frame has moved.
     */
    private function redirectToContentLanguage(Route $route): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || MagicfrontUtils::isCanvasFetch()
            || MagicfrontUtils::isContentOnlyRequest()
        ) {
            return;
        }
        $languageUrls = [];
        foreach ($route->getAvailableLanguages() as $available) {
            $languageUrls[$available->getCode()] = $available->getUrl();
        }
        $asked  = $_GET[self::MFF_LANG] ?? null;
        $target = MagicfrontUtils::languageRedirectUrl(
            is_string($asked) ? $asked : null,
            MagicfrontToken::getToken() !== null,
            $route->getLanguage(),
            $languageUrls,
            (string) ($_SERVER['QUERY_STRING'] ?? '')
        );
        if ($target !== null) {
            Response::redirect($target, 302);
        }
    }

    // ─── Batch / data hooks ────────────────────────────────────────────────

    /**
     * Content locale for the MFF widget fetch. Honors the optional `mff_lang` preview override
     * (storefront-contract) so the editor can preview a page in a specific language — e.g. the
     * template gallery previews in English regardless of the shop's primary locale. Falls back to
     * the route language when the override is absent (normal storefront + editor-canvas editing).
     */
    private function resolveContentLanguage(): string {
        return MagicfrontUtils::contentLanguage($this->route->getLanguage());
    }

    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if (!RenderMode::isPreviewMode() || !$this->pageId) {
            return;
        }
        // Chrome-only render: `page` carries the CHROME document id (the canvas requires a non-empty
        // page on every widget re-render), not a page. Fetching a page with it would 404 twice per load
        // and there is no page body to paint anyway — the chrome then resolves from the commerce
        // DEFAULT docs, which is exactly what Personalización edits.
        if (MagicfrontUtils::chromeOnlyRegion() !== null) {
            return;
        }
        Utils::addTimerDebugFlag('mf-batchPageWidgets', Timer::START_SUFFIX);
        $instances       = $this->widgetsService->getPageWidgetInstances($this->pageId, $this->resolveContentLanguage());
        Utils::addTimerDebugFlag('mf-batchPageWidgets', Timer::END_SUFFIX);
        $this->widgets   = WidgetTypeCollector::flatten($instances);
        $this->pages     = WidgetToPageTransformer::transform(new ElementCollection(['items' => $instances]));
        Utils::addTimerDebugFlag('mf-batchChromeRefs', Timer::START_SUFFIX);
        $facts            = $this->widgetsService->getPageFacts($this->pageId);
        // A Studio document has no chrome: the store widget is painted alone.
        $this->pageChrome = $this->studioDocument ? [] : $facts->getChrome();
        $this->pageType   = $facts->getPageType();
        $this->studioPlacement = $this->studioDocument ? $facts->getStudioPlacement() : '';
        Utils::addTimerDebugFlag('mf-batchChromeRefs', Timer::END_SUFFIX);
    }

    protected function setMagicfrontData(): void {
        Utils::addTimerDebugFlag('mf-loadTemplates', Timer::START_SUFFIX);
        $templates = RenderMode::isPreviewMode()
            ? $this->loadEditorTemplates()
            : $this->loadStorefrontData();
        Utils::addTimerDebugFlag('mf-loadTemplates', Timer::END_SUFFIX);
        Utils::addTimerDebugFlag('mf-emit', Timer::START_SUFFIX);
        $this->emitMagicfrontData($templates);
        Utils::addTimerDebugFlag('mf-emit', Timer::END_SUFFIX);
    }

    // ─── Editor path (dcsapi) ──────────────────────────────────────────────

    /**
     * $this->widgets is already populated by setMagicfrontBatchData; the editor
     * path only needs the matching widget templates from dcsapi.
     *
     * @return array
     */
    private function loadEditorTemplates(): array {
        $types = WidgetTypeCollector::templateKeysFromWidgets($this->widgets);
        return $types !== []
            ? $this->widgetsService->getWidgetTemplatesForTypes($types)
            : [];
    }

    // ─── Storefront path ───────────────────────────────────────────────────

    /**
     * Reads the published blob of the route's magicfront page (via {@see magicfrontPage()}, the generic
     * mff_* resolver keyed by route type), whose pageContent carries both widgets and templates. NO
     * dcsapi — customers are unauthenticated for that API.
     *
     * Side-effects: sets $this->widgets / $this->pages / $this->pageId.
     *
     * @return array
     */
    private function loadStorefrontData(): array {
        $pageDto = $this->magicfrontPage();
        if (!$pageDto instanceof Page) {
            return [];
        }
        $document = PageDocument::fromJson($pageDto->getLanguage()?->getPageContent());
        if ($document === null) {
            return [];
        }
        $this->widgets  = WidgetTypeCollector::flatten($document->widgets()?->getItems() ?? []);
        $this->pages    = $document->toPages();
        $this->pageId   = (string) $pageDto->getId();
        $this->pageType = $document->pageType();
        return $document->templatesById();
    }

    // ─── Shared output ─────────────────────────────────────────────────────

    /**
     * @param array $templates
     */
    private function emitMagicfrontData(array $templates): void {
        $this->pages = PageRelationResolver::setData($this->pages);
        // Product-detail routes attach the route's product to every widget page so the
        // product-detail widgets read it as `page.product` (raw SDK Product). No-op elsewhere.
        PageRelationResolver::attachProduct($this->pages, $this->routeProduct());
        // Custom-tag NAMES (pId → localized name) so specs can label the product's customTagValues.
        PageRelationResolver::attachProductCustomTags($this->pages, $this->routeProductCustomTags());
        // Related-items GROUPS (name + products) so productSiblings can show one group as swatches.
        PageRelationResolver::attachProductRelatedGroups($this->pages, $this->routeProductRelatedGroups());
        // Category-listing routes attach the route's category (`page.category`) + its product
        // LIST (`page.products` on widgets without their own categoryId). No-op elsewhere.
        PageRelationResolver::attachCategory($this->pages, $this->routeCategory(), $this->routeSubcategories());
        // Unified product LIST → `page.products` for the productList widget, regardless of route:
        // category routes supply the category listing, product routes the related-products list.
        PageRelationResolver::attachProducts($this->pages, $this->routeProducts());
        PageRelationResolver::attachBreadcrumb($this->pages, $this->sampleCategoryBreadcrumb()
            ?? $this->sampleProductBreadcrumb()
            ?? BreadcrumbResolver::build($this->getRoute(), $this->magicfrontPage()));
        // Account/session widgets attach via the provider registry (runs when their widget type is
        // present, independent of route — the basis for placing account widgets on any page).
        Utils::addTimerDebugFlag('mf-providerAttach', Timer::START_SUFFIX);
        $this->dispatchProviderAttach();
        Utils::addTimerDebugFlag('mf-providerAttach', Timer::END_SUFFIX);
        // Product-detail routes attach the bundle definitions → page.productBundles. No-op elsewhere.
        PageRelationResolver::attachProductBundles($this->pages, $this->routeProductBundles());
        // Product-detail routes attach related-items groups → page.relatedItems. No-op elsewhere.
        // Product-related lists keyed by pId → page.productRelated[<id>] for the productList widget.
        PageRelationResolver::attachProductRelated($this->pages, $this->routeProductRelated());
        // Product-detail routes attach approved comments → page.comments. No-op elsewhere.
        PageRelationResolver::attachComments($this->pages, $this->routeComments());
        // Product-detail routes attach option labels/constants → page.bundleLabels. No-op elsewhere.
        PageRelationResolver::attachBundleLabels($this->pages, $this->routeBundleLabels());
        // Product-detail routes attach the full product JSON → page.productJson (buyForm data-product). No-op elsewhere.
        PageRelationResolver::attachProductJson($this->pages, $this->routeProductJson());
        // Product-detail routes attach login-aware wishlist state → page.wishlist. No-op elsewhere.
        PageRelationResolver::attachWishlist($this->pages, $this->routeWishlist());
        // Product-detail routes attach native comment-form wiring → page.commentForm. No-op elsewhere.
        PageRelationResolver::attachCommentForm($this->pages, $this->routeCommentForm());
        if (MagicfrontUtils::chromeOnlyRegion() === null) {
            Utils::addTimerDebugFlag('mf-providerShared', Timer::START_SUFFIX);
            $this->dispatchProviderShared();
            Utils::addTimerDebugFlag('mf-providerShared', Timer::END_SUFFIX);
        }
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTypes = WidgetTypeCollector::fromWidgets($this->widgets);

        $widgetTemplateList = [];
        foreach ($templates as $type => $template) {
            $widgetTemplateList[$type] = $template->getTemplateHtml();
        }

        $canvasMode = RenderMode::isCanvasMode();
        $previewMode = !empty($this->getRequestParam(self::MFF_PREVIEW, false, null));
        // Per-controller hook to post-process the widget template list before it ships. Generic no-op by
        // default; controllers that own an interactive/commerce surface (e.g. checkout) override it to
        // rewrite their widget's replica into the store's runtime hooks. Storefront-only: in the editor
        // canvas / preview the templates ship untouched (static styled mock).
        $widgetTemplateList = $this->transformWidgetTemplates(
            $widgetTemplateList,
            !RenderMode::isPreviewMode() && !$previewMode
        );
        // Content-only partial render skips the asset bundle: the widget CSS/JS is already in the
        // document from the initial full load, so the tab-swap response ships HTML only.
        $showAssets = !MagicfrontUtils::isContentOnlyRequest()
            && (!$canvasMode || $previewMode) && !empty($this->pageId) && !empty($widgetTypes);
        $assets = $showAssets
            ? (new WidgetAssetsBuilder())->build($this->widgets, $templates)
            : ['css' => '', 'js' => ''];

        $this->setDataValue(MagicfrontControllerData::WIDGET_TEMPLATE_LIST, $widgetTemplateList);
        $this->setDataValue(MagicfrontControllerData::WIDGET_TYPES, $widgetTypes);
        $this->setDataValue(MagicfrontControllerData::PAGE, $this->pageId);
        $this->setDataValue(MagicfrontControllerData::PAGE_CHROME, $this->pageChrome);
        $this->setDataValue(MagicfrontControllerData::STUDIO_DOCUMENT, $this->studioDocument);
        $this->setDataValue(MagicfrontControllerData::STUDIO_PLACEMENT, $this->studioPlacement);
        $this->setDataValue(MagicfrontControllerData::ASSETS_URL, Environment::get('MF_ASSETS_URL'));
        $this->setDataValue(MagicfrontControllerData::CANVAS_MODE, $canvasMode);
        $this->setDataValue(MagicfrontControllerData::PREVIEW_MODE, $previewMode);
        $this->setDataValue(MagicfrontControllerData::SHOW_ASSETS, $showAssets);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_CSS, $assets['css']);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_JS, $assets['js']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * The magicfront render source for the current route, resolved once. Routes backed by a singleton
     * mff_* page ({@see SpecialPagePId::forRouteType}: home / category / product / blog) read it via the
     * LC FOB blob by pId (NO dcsapi — customers are unauthenticated for it); controllers keep their own
     * CONTROLLER_ITEM (real product/category/post) untouched. Routes with no singleton page (PAGE /
     * pageModules) carry their own per-page blob, so the route's CONTROLLER_ITEM is the source. Null when
     * neither yields a Page → the trait renders nothing.
     */
    protected function magicfrontPage(): ?Page {
        if (!$this->magicfrontPageResolved) {
            $this->magicfrontPageResolved = true;
            $pId = SpecialPagePId::forRouteType((string) $this->getRoute()?->getType());
            $page = $pId !== null
                ? $this->loadPageByPId($pId)
                : $this->getControllerData(Controller::CONTROLLER_ITEM);
            $this->magicfrontPageCache = $page instanceof Page ? $page : null;
        }
        return $this->magicfrontPageCache;
    }

    /**
     * Reads a single published page by its stable pId via the LC FOB — the one the route's existence check already
     * fetched in this request when there is one ({@see PluginProperties::loadSpecialPage()}).
     */
    private function loadPageByPId(string $pId): ?Page {
        return PluginProperties::loadSpecialPage($pId);
    }

    // ─── Widget data providers ─────────────────────────────────────────────

    /**
     * Per-widget-type data providers run by {@see self::dispatchProviderAttach()}. Keyed by widget
     * TYPE (not route), so their data attaches wherever the widget is placed. Phase 0: only the
     * account/session group; the route-entity groups (product/category) still attach directly in
     * emitMagicfrontData and will migrate here incrementally.
     *
     * @return WidgetDataProvider[]
     */
    protected function providers(): array {
        return ProviderRegistry::all();
    }

    /** Immutable context a provider reads: route + session + present widget types + a data resolver. */
    private function buildProviderContext(mixed $routeEntity = null): ProviderContext {
        return new ProviderContext(
            $this->route,
            $this->getSession(),
            $this->activeWidgetTypes(),
            fn(string $key): mixed => $this->getControllerData($key),
            $routeEntity,
            RenderMode::isCanvasMode(),
        );
    }

    /**
     * Widget types whose data should actually be fetched for THIS request. Identical to the full type
     * set except inside a panel widget (userPanel + future merchant panels): the childStructure panels
     * render lazily (only the active `?mfPanel` section is shown), so data for the OTHER panels is
     * wasted work. Trims to: every type outside any panel ∪ the active panel's subtree types. Types
     * that also live outside a panel, or in the active panel, are always kept. Editor/preview is never
     * trimmed (the canvas renders every panel). See {@see PanelUrl}.
     *
     * @return string[]
     */
    private function activeWidgetTypes(): array {
        $items    = $this->pages?->getItems() ?? [];
        $baseline = WidgetTypeCollector::fromPages($items);
        if ($baseline === []) {
            return $baseline;
        }
        // Trim to the active panel in EVERY mode (storefront AND editor/preview): only the active panel
        // renders inline now (others load via the widgetContent AJAX on switch), and preview panels use
        // their mff_previewMode() mock rather than this fetched data — so fetching every panel's data is
        // pure waste in all modes. This is what stops an account page from firing N account-API calls.
        $keep = [];
        $this->collectKeepTypes($items, PanelUrl::activeSection(), $keep);
        return $keep === [] ? $baseline : array_values(array_unique($keep));
    }

    /**
     * Collect the widget types worth fetching for THIS request, by NODE (not by type-set arithmetic):
     * walk the tree and record every real widget's type, but SKIP the subtree of every INACTIVE panel
     * (a childStructure pseudo of a panel host that isn't the active one). A type that also appears on a
     * node outside any panel, or inside the active panel, is still collected via that node — so a
     * standalone widget of the same type as an inactive panel keeps its data (fixes the type-diff bug).
     *
     * Pseudo detection uses {@see self::isPanelPseudo()} (no slotId) — the reliable, storefront-safe test
     * matching the template, so root slots[] children (WITH a slotId, e.g. userPanel's `accountForms`)
     * are never mistaken for panels. (Aligning with the DTO's autoGenerated-based check is deferred — an
     * unpopulated autoGenerated on the FOB blob would silently disable trimming.)
     *
     * Active-panel resolution mirrors the template: with a valid `?mfPanel`, the matching pseudo; with an
     * absent/unknown one, the prefix up to and including the first pseudo that has NO `gatingFlag` (that
     * one is visible to every user, so the template's "first visible" pick is provably within the prefix).
     *
     * @param array $items
     * @param string[]          $keep
     */
    private function collectKeepTypes(array $items, string $activeSection, array &$keep): void {
        foreach ($items as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $type = $page->getCustomType();
            if ($type !== '') {
                $keep[] = $type;
            }
            $subpages = $page->getSubpages();
            $pseudos  = [];
            foreach ($subpages as $sp) {
                if ($sp instanceof Page && $this->isPanelPseudo($sp)) {
                    $pseudos[] = $sp;
                }
            }
            if ($pseudos === []) {
                $this->collectKeepTypes($subpages, $activeSection, $keep);
                continue;
            }
            $effective = $activeSection;
            if ($effective !== '') {
                $known = false;
                foreach ($pseudos as $pseudo) {
                    if ((string) ($pseudo->getModuleSettings()['sectionKey'] ?? '') === $effective) {
                        $known = true;
                        break;
                    }
                }
                if (!$known) {
                    $effective = '';
                }
            }
            $reachedUngated = false;
            foreach ($subpages as $sp) {
                if (!$sp instanceof Page) {
                    continue;
                }
                if (!$this->isPanelPseudo($sp)) {
                    // Root slot child (e.g. accountForms) — never a panel; always keep it and its subtree.
                    $this->collectKeepTypes([$sp], $activeSection, $keep);
                    continue;
                }
                $ms = $sp->getModuleSettings();
                // Title/link pseudos are NOT loadable content panels — a title renders inline (no content),
                // a lcFunction item is an action link. Mirror the template's activePanel pick, which skips
                // both; otherwise the first title would be taken as the active panel and the real first
                // section's data would never be fetched. They also must not consume the "first ungated" slot.
                if (!empty($ms['isTitle']) || (string) ($ms['lcFunction'] ?? '') !== '') {
                    continue;
                }
                $sk       = (string) ($ms['sectionKey'] ?? '');
                $isActive = false;
                if ($effective !== '') {
                    $isActive = $sk !== '' && $sk === $effective;
                } elseif (!$reachedUngated) {
                    $isActive = true;
                    if ((string) ($sp->getModuleSettings()['gatingFlag'] ?? '') === '') {
                        $reachedUngated = true;
                    }
                }
                if ($isActive) {
                    $this->collectKeepTypes([$sp], $activeSection, $keep);
                }
                // Inactive pseudo: skip its subtree — its types are fetched only when it becomes active.
            }
        }
    }

    /** A childStructure panel pseudo: a direct child with no typed slotId (root slot children carry one). */
    private function isPanelPseudo(Page $page): bool {
        return $page->getSlotId() === null || $page->getSlotId() === '';
    }

    /** Run every applicable provider's attach() over the widget pages. */
    private function dispatchProviderAttach(): void {
        $ctx = $this->buildProviderContext();
        foreach ($this->providers() as $provider) {
            if ($provider->appliesTo($ctx)) {
                $provider->attach($this->pages, $ctx);
            }
        }
    }

    /** Merge applicable providers' sharedData() over the always-on base into the page-level `shared` container (mffShared). */
    private function dispatchProviderShared(): void {
        $ctx = $this->buildProviderContext();
        $this->setDataValue(MagicfrontControllerData::SHARED, ProviderRegistry::collectShared($this->providers(), $ctx));
    }

    /**
     * The product-detail route's product. The ProductController overrides this with the FWK-resolved
     * route product. Every other controller gets the default: MagicFront's SAMPLE product when the page
     * being painted is of type PRODUCT and we are in the editor/preview path (the canvas loads any page
     * from the shop root, so a product page there has no route product), null otherwise. The sample
     * hydrates the very same SDK `Product` DTO a real route yields, so widgets and the `mff_product_*`
     * helpers read one contract. Off the preview path a page never gets the sample: no data, no demo.
     */
    protected function routeProduct(): ?Product {
        if (!$this->paintsSampleProduct()) {
            return null;
        }
        $sample = $this->productSample();
        return is_array($sample['product'] ?? null) ? new Product($sample['product']) : null;
    }

    /**
     * Product custom tags (pId → `{name, controlType}`) → `page.productCustomTags`. The
     * ProductController overrides this with the commerce's `GET /customTags?type=PRODUCT`; the default
     * returns the sample's own `customTags` when the sample product is painted, empty otherwise.
     *
     * @return array
     */
    protected function routeProductCustomTags(): array {
        if (!$this->paintsSampleProduct()) {
            return [];
        }
        return PageRelationResolver::sampleCustomTags($this->productSample());
    }

    /**
     * The product's related-items groups (`[{name, products: [Product…]}]`) → `page.productRelatedGroups`.
     * ProductController overrides this with the route product's real groups; the default returns the
     * sample's own `relatedGroups` when the sample product is painted, empty otherwise.
     */
    protected function routeProductRelatedGroups(): array {
        if (!$this->paintsSampleProduct()) {
            return [];
        }
        return PageRelationResolver::sampleRelatedGroups($this->productSample());
    }

    /**
     * The route entity's related-items GROUPS, each with its name and products, for the productSiblings
     * widget — fetched only when a page carries one (one API call per product page otherwise saved).
     * Unlike {@see self::buildProductRelated()} the groups are kept apart: the widget shows ONE group,
     * picked by name by the merchant ("Colors").
     *
     * @return array
     */
    protected function buildProductRelatedGroups(int $entityId, object $service): array {
        if ($entityId <= 0 || !method_exists($service, 'getRelatedItems')) {
            return [];
        }
        if (!$this->hasWidgetType($this->pages?->getItems() ?? [], 'productSiblings')) {
            return [];
        }
        $related = $this->relatedItems($entityId, $service, false);
        if ($related === null) {
            return [];
        }
        $groups = [];
        foreach ($related->getItems() as $group) {
            if (is_object($group) && method_exists($group, 'getProducts') && method_exists($group, 'getName')) {
                $groups[] = ['name' => (string) $group->getName(), 'products' => $group->getProducts()];
            }
        }
        return $groups;
    }

    private function paintsSampleProduct(): bool {
        return $this->pageType === MagicfrontPageType::PRODUCT
            && RenderMode::isPreviewMode()
            && $this->widgetsService instanceof WidgetsService;
    }

    /**
     * The sample category's own trail (Home › … › the sample category) while it is painted, so the breadcrumb
     * reads like the category it sits on instead of the shop root's one-crumb trail; null otherwise.
     */
    private function sampleCategoryBreadcrumb(): ?array {
        if (!$this->paintsSampleCategory()) {
            return null;
        }
        $trail = $this->categorySample()['breadcrumb'] ?? null;
        return is_array($trail) && $trail !== [] ? array_values(array_filter($trail, 'is_array')) : null;
    }

    /**
     * The sample product's own trail (Home › … › the sample product) while it is painted OFF a real product
     * route, so a product page previewed from the shop root shows a breadcrumb like the product it sits on;
     * null otherwise (a real product route keeps its own trail).
     */
    private function sampleProductBreadcrumb(): ?array {
        if (!$this->paintsSampleProduct() || (string) $this->getRoute()?->getType() === RouteType::PRODUCT) {
            return null;
        }
        $trail = $this->productSample()['breadcrumb'] ?? null;
        return is_array($trail) && $trail !== [] ? array_values(array_filter($trail, 'is_array')) : null;
    }

    private function paintsSampleCategory(): bool {
        return $this->pageType === MagicfrontPageType::CATEGORY
            && (string) $this->getRoute()?->getType() !== RouteType::CATEGORY
            && RenderMode::isPreviewMode()
            && $this->widgetsService instanceof WidgetsService;
    }

    private function categorySample(): array {
        if ($this->categorySampleCache === null) {
            $this->categorySampleCache = $this->widgetsService->getSample(
                MagicfrontPageType::SAMPLE_KIND[MagicfrontPageType::CATEGORY],
                $this->resolveContentLanguage(),
                SampleSituationParam::fromRequest(fn(string $p): mixed => $this->getRequestParam($p, false, null))
            );
        }
        return $this->categorySampleCache;
    }

    /**
     * One collection of the category sample (`{items, pagination}`) as the SDK ElementCollection a real
     * category route yields, each item hydrated into `$dtoClass`. Null when the sample does not carry it.
     */
    private function sampleCollection(string $key, string $dtoClass): ?ElementCollection {
        return PageRelationResolver::sampleCollection($this->categorySample(), $key, $dtoClass);
    }

    private function productSample(): array {
        if ($this->productSampleCache === null) {
            $this->productSampleCache = $this->widgetsService->getSample(
                MagicfrontPageType::SAMPLE_KIND[MagicfrontPageType::PRODUCT],
                $this->resolveContentLanguage(),
                SampleSituationParam::fromRequest(fn(string $p): mixed => $this->getRequestParam($p, false, null))
            );
        }
        return $this->productSampleCache;
    }

    /**
     * The category-listing route's category, or null. Only the CategoryController overrides this
     * (returning the FWK-resolved route category); other controllers keep the null default so
     * `attachCategory` is a no-op. In the editor/preview path a CATEGORY page (loaded from the shop root, so
     * with no route category) gets MagicFront's SAMPLE category instead, hydrated into the same SDK
     * `Category` a real route yields — the counterpart of {@see self::routeProduct()} on product pages.
     */
    protected function routeCategory(): ?Category {
        if (!$this->paintsSampleCategory()) {
            return null;
        }
        $category = $this->categorySample()['category'] ?? null;
        return is_array($category) ? new Category($category) : null;
    }

    /**
     * The account route's logged-in account view-model as an array, or [] off the account route.
     * Only the AccountController overrides this; other controllers keep the empty default so
     * `attachAccount` is a no-op. Empty in the editor/docker preview → the accountPage widget mocks.
     */
    protected function routeAccount(): array {
        return [];
    }

    /**
     * Post-process the built widget template list (type → templateHtml) before it ships to the render.
     * Generic no-op default; a controller that owns an interactive/commerce surface overrides this to
     * rewrite its widget's presentational replica into the store's real runtime hooks. `$storefront` is
     * false in the editor canvas / preview so overrides can keep the static mock there.
     *
     * @param array $widgetTemplateList type => templateHtml
     * @return array
     */
    protected function transformWidgetTemplates(array $widgetTemplateList, bool $storefront): array {
        return $widgetTemplateList;
    }

    /** The unified product LIST attached to `page.products`. CategoryController returns the category
     *  listing; other controllers keep null so `attachProducts` is a no-op. Lets the productList
     *  widget read one variable on any route. */
    protected function routeProducts(): ?ElementCollection {
        if (!$this->paintsSampleCategory()) {
            return null;
        }
        return $this->sampleCollection('products', Product::class);
    }

    /** Product-related lists keyed by pId, attached to `page.productRelated`. Only ProductController
     *  overrides this (fetching each block by pId); other controllers keep the empty default so
     *  `attachProductRelated` is a no-op.
     *
     * @return array */
    protected function routeProductRelated(): array {
        return [];
    }

    /**
     * Build `page.productRelated` — a flat list of the related PRODUCTS for the current route entity
     * ($entityId = the route product OR category id). Shared by ProductController and CategoryController,
     * both passing the SDK service (Product/Category) that exposes `getRelatedItems()`. On a category
     * route `$categoryProducts` must be true — the SDK only returns the category's related PRODUCTS when
     * that flag is set (see {@see RelatedItemsParametersGroup::setCategoryProducts()}).
     *
     * pId-based block filtering is temporarily disabled: every related block for the entity is fetched
     * and its products flattened, so the productRelated widget just prints them.
     *
     * @return array
     */
    protected function buildProductRelated(int $entityId, object $service, bool $categoryProducts = false): array {
        if ($entityId <= 0 || !method_exists($service, 'getRelatedItems')) {
            return [];
        }
        if (!$this->hasWidgetType($this->pages?->getItems() ?? [], 'productRelated')) {
            return [];
        }
        $related = $this->relatedItems($entityId, $service, $categoryProducts);
        if ($related === null) {
            return [];
        }
        $products = [];
        foreach ($related->getItems() as $group) {
            if (is_object($group) && method_exists($group, 'getProducts')) {
                $products = array_merge($products, $group->getProducts());
            }
        }
        return $products;
    }

    private function relatedItems(int $entityId, object $service, bool $categoryProducts): ?ElementCollection {
        $key = spl_object_id($service) . ':' . $entityId . ':' . (int) $categoryProducts;
        if (!array_key_exists($key, $this->relatedItemsCache)) {
            $params = new RelatedItemsParametersGroup();
            if ($categoryProducts) {
                $params->setCategoryProducts(true);
            }
            $related = $service->getRelatedItems($entityId, '', $params);
            $this->relatedItemsCache[$key] = $related instanceof ElementCollection ? $related : null;
        }
        return $this->relatedItemsCache[$key];
    }

    /* pId-based related-block filtering temporarily disabled — kept for re-enabling later.
    private const RELATED_ITEMS_LIMIT_MAX = 50;

    private function collectRelatedBlocks(array $pages): array {
        $blocks = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === 'productList') {
                $settings = $page->getModuleSettings();
                $pId = $settings['relatedId'] ?? '';
                if (is_string($pId) && $pId !== '') {
                    $limit = (int) ($settings['productCount'] ?? 0);
                    $blocks[$pId] = max($blocks[$pId] ?? 0, $limit);
                }
            }
            foreach ($this->collectRelatedBlocks($page->getSubpages() ?? []) as $childPId => $childLimit) {
                $blocks[$childPId] = max($blocks[$childPId] ?? 0, $childLimit);
            }
        }
        return $blocks;
    }
    */

    /**
     * The `productCount` (products per page) of the route-listing productList widget in a template
     * page blob — the productList that has NO own `categoryId`, i.e. the one bound to the route
     * `page.products`. Used by CategoryController to drive the category fetch `perPage` (and thus the
     * pagination) from the merchant's widget setting. Null when the blob has no such widget/count.
     */
    protected function listingPerPage(Page $templatePage): ?int {
        $document = PageDocument::fromJson($templatePage->getLanguage()?->getPageContent());
        if ($document === null) {
            return null;
        }
        return $this->findListingProductCount($document->toPages()?->getItems() ?? []);
    }

    /**
     * @param Page[] $pages
     */
    private function findListingProductCount(array $pages): ?int {
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === 'productList') {
                $settings = $page->getModuleSettings();
                $categoryId = $settings['categoryId'] ?? '';
                if ($categoryId === '' || (int) $categoryId === 0) {
                    $count = (int) ($settings['productCount'] ?? 0);
                    if ($count > 0) {
                        return $count;
                    }
                }
            }
            $child = $this->findListingProductCount($page->getSubpages() ?? []);
            if ($child !== null) {
                return $child;
            }
        }
        return null;
    }

    /**
     * Whether the page tree contains at least one widget of the given customType. Used to skip the
     * related-items API call on pages that have no productRelated widget.
     *
     * @param Page[] $pages
     */
    private function hasWidgetType(array $pages, string $type): bool {
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === $type) {
                return true;
            }
            if ($this->hasWidgetType($page->getSubpages() ?? [], $type)) {
                return true;
            }
        }
        return false;
    }

    /** The route category's child categories, attached to `page.categories` of the subcategoryGrid
     *  widget. Null off a category route. Overridden by CategoryController. */
    protected function routeSubcategories(): ?ElementCollection {
        if (!$this->paintsSampleCategory()) {
            return null;
        }
        return $this->sampleCollection('subcategories', Category::class);
    }

    /**
     * The product-detail route's bundle definitions, attached to every widget page as
     * `page.productBundles`. Only the ProductController overrides this; other controllers keep the
     * null default so `attachProductBundles` is a no-op. Null in the editor/docker preview.
     */
    protected function routeProductBundles(): ?BundleDefinitionsWithGroupings {
        return null;
    }

    /**
     * The product-detail route's approved comments (SDK Comment[]), attached to every widget page as
     * `page.comments` so the productComments widget lists real reviews instead of the inline mock.
     * Only the ProductController overrides this; other controllers keep the [] default (no-op).
     *
     * @return array
     */
    protected function routeComments(): array {
        if (!$this->paintsSampleProduct()) {
            return [];
        }
        return PageRelationResolver::sampleComments($this->productSample());
    }

    /**
     * The product-detail route's option labels/constants (Sí/No, upload labels, date pattern, missing
     * image), attached to every widget page as `page.bundleLabels`. Only ProductController overrides
     * this; other controllers keep the [] default so `attachBundleLabels` is a no-op.
     *
     * @return array
     */
    protected function routeBundleLabels(): array {
        return [];
    }

    /**
     * The product-detail route's full product JSON (FWK ProductJsonData shape), attached to every
     * widget page as `page.productJson` so the productAddToCart widget's buyForm carries a complete
     * `data-product` (LC's lc.forms.js needs definition + priceByQuantity, not just the id). Only
     * ProductController overrides this; other controllers keep the [] default (no-op).
     *
     * @return array
     */
    protected function routeProductJson(): array {
        return [];
    }

    /**
     * The product-detail route's login-aware wishlist state ({isLogged, ids, labelAdd, labelDelete}),
     * attached to every widget page as `page.wishlist` so the productAddToCart wishlist button emits
     * the right LC hook (data-wishlist-account_required when anonymous, -add / -delete when logged in).
     * Only ProductController overrides this; other controllers keep the [] default (no-op).
     *
     * @return array
     */
    protected function routeWishlist(): array {
        return [];
    }

    /**
     * The product-detail route's native comment-form wiring ({action, canComment}), attached to every
     * widget page as `page.commentForm` so the productComments widget posts to the real ADD_COMMENT
     * endpoint (LC's productAddCommentForm) and gates the form behind login when anonymous rating is
     * disabled. Only ProductController overrides this; other controllers keep the [] default (no-op).
     *
     * @return array
     */
    protected function routeCommentForm(): array {
        return [];
    }

    protected function isCacheable(): bool {
        if (MagicfrontUtils::isIframeRequest()) {
            return false;
        }
        return parent::isCacheable();
    }
}
