<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\Controllers\Controller;
use FWK\Core\FilterInput\FilterInput;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BreadcrumbResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetAssetsBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use SDK\Core\Dtos\ElementCollection;
use SDK\Services\Parameters\Groups\PageParametersGroup;
use SDK\Services\Parameters\Groups\RelatedItemsParametersGroup;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Resources\Environment;
use SDK\Dtos\Catalog\Category;
use SDK\Dtos\Catalog\Page\Page;
use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Dtos\Common\Route;
use SDK\Enums\RouteType;

/**
 * Mixes MagicFront-aware batch/data hooks into HTML controllers (Home,
 * Page\Page). Two paths, dispatched by isEditor() — true iff the request
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
    public const MFF_LANG = 'mff_lang';

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $pageId = null;

    protected bool $editorMode = false;

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
     * @var array{header?: string, footer?: string}
     */
    protected array $pageChrome = [];

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
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;

        $rawToken = $this->getRequestParam(MagicfrontToken::MF_TOKEN, false, null);
        $this->editorMode = MagicfrontUtils::isCanvasMode() || !empty($rawToken);

        if (!$this->editorMode) {
            return;
        }
        if (MagicfrontUtils::isCanvasMode()) {
            MagicfrontToken::setToken($rawToken);
        }
        $this->widgetsService = WidgetsService::getInstance();
        $this->pageId = $this->getRequestParam(Parameters::PAGE, false, null)
            ?? $this->widgetsService->getPageId((string)$route->getId());
    }

    // ─── Batch / data hooks ────────────────────────────────────────────────

    /**
     * Content locale for the MFF widget fetch. Honors the optional `mff_lang` preview override
     * (storefront-contract) so the editor can preview a page in a specific language — e.g. the
     * template gallery previews in English regardless of the shop's primary locale. Falls back to
     * the route language when the override is absent (normal storefront + editor-canvas editing).
     */
    private function resolveContentLanguage(): string {
        $override = $this->getRequestParam(self::MFF_LANG, false, null);
        if (is_string($override) && $override !== '') {
            return $override;
        }
        return $this->route->getLanguage();
    }

    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if (!$this->isEditor() || !$this->pageId) {
            return;
        }
        $instances       = $this->widgetsService->getPageWidgetInstances($this->pageId, $this->resolveContentLanguage());
        $this->widgets   = WidgetTypeCollector::flatten($instances);
        $this->pages     = WidgetToPageTransformer::transform(new ElementCollection(['items' => $instances]));
        $this->pageChrome = $this->widgetsService->getPageChromeRefs($this->pageId);
    }

    protected function setMagicfrontData(): void {
        $templates = $this->isEditor()
            ? $this->loadEditorTemplates()
            : $this->loadStorefrontData();
        $this->emitMagicfrontData($templates);
    }

    // ─── Editor path (dcsapi) ──────────────────────────────────────────────

    /**
     * $this->widgets is already populated by setMagicfrontBatchData; the editor
     * path only needs the matching widget templates from dcsapi.
     *
     * @return array<string, WidgetTemplate>
     */
    private function loadEditorTemplates(): array {
        $types = WidgetTypeCollector::fromWidgets($this->widgets);
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
     * @return array<string, WidgetTemplate>
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
        $this->widgets = WidgetTypeCollector::flatten($document->widgets()?->getItems() ?? []);
        $this->pages   = $document->toPages();
        $this->pageId  = (string) $pageDto->getId();
        return $document->templatesById();
    }


    // ─── Shared output ─────────────────────────────────────────────────────

    /**
     * @param array<string, WidgetTemplate> $templates
     */
    private function emitMagicfrontData(array $templates): void {
        $this->pages = PageRelationResolver::setData($this->pages);
        // Product-detail routes attach the route's product to every widget page so the
        // product-detail widgets read it as `page.product` (raw SDK Product). No-op elsewhere.
        PageRelationResolver::attachProduct($this->pages, $this->routeProduct());
        // Category-listing routes attach the route's category (`page.category`) + its product
        // LIST (`page.products` on widgets without their own categoryId). No-op elsewhere.
        PageRelationResolver::attachCategory($this->pages, $this->routeCategory(), $this->routeSubcategories());
        // Unified product LIST → `page.products` for the productList widget, regardless of route:
        // category routes supply the category listing, product routes the related-products list.
        PageRelationResolver::attachProducts($this->pages, $this->routeProducts());
        PageRelationResolver::attachBreadcrumb($this->pages, BreadcrumbResolver::build($this->getRoute(), $this->magicfrontPage()));
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
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTypes = WidgetTypeCollector::fromWidgets($this->widgets);

        $widgetTemplateList = [];
        foreach ($templates as $type => $template) {
            $widgetTemplateList[$type] = $template->getTemplateHtml();
        }

        $canvasMode = MagicfrontUtils::isCanvasMode();
        $previewMode = !empty($this->getRequestParam(self::MFF_PREVIEW, false, null));
        $showAssets = (!$canvasMode || $previewMode) && !empty($this->pageId) && !empty($widgetTypes);
        $assets = $showAssets
            ? (new WidgetAssetsBuilder())->build($this->widgets, $templates)
            : ['css' => '', 'js' => ''];

        $this->setDataValue(MagicfrontControllerData::WIDGET_TEMPLATE_LIST, $widgetTemplateList);
        $this->setDataValue(MagicfrontControllerData::WIDGET_TYPES, $widgetTypes);
        $this->setDataValue(MagicfrontControllerData::PAGE, $this->pageId);
        $this->setDataValue(MagicfrontControllerData::PAGE_CHROME, $this->pageChrome);
        $this->setDataValue(MagicfrontControllerData::ASSETS_URL, Environment::get('MF_ASSETS_URL'));
        $this->setDataValue(MagicfrontControllerData::CANVAS_MODE, $canvasMode);
        $this->setDataValue(MagicfrontControllerData::PREVIEW_MODE, $previewMode);
        $this->setDataValue(MagicfrontControllerData::SHOW_ASSETS, $showAssets);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_CSS, $assets['css']);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_JS, $assets['js']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Editor mode is decided once in magicfrontInit() — see $editorMode for
     * the signals (Sec-Fetch-Dest: iframe OR a non-empty mfToken on the URL).
     */
    private function isEditor(): bool {
        return $this->editorMode;
    }

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
     * Reads a single published page by its stable pId via the LC FOB. Uses getPages()->getItems()[0]
     * (not PageService::getPageByPId, whose ?Page return type mismatches its ?ElementCollection body).
     */
    private function loadPageByPId(string $pId): ?Page {
        $params = new PageParametersGroup();
        $params->setPId($pId);
        $collection = Loader::service(Services::PAGE)->getPages($params);
        if (!$collection instanceof ElementCollection) {
            return null;
        }
        $page = $collection->getItems()[0] ?? null;
        return $page instanceof Page ? $page : null;
    }

    /**
     * The product-detail route's product, or null. Only the ProductController overrides this
     * (returning the FWK-resolved route product); Home/Page controllers keep the null default,
     * so `attachProduct` is a no-op for them. Null in the editor/docker preview → widgets mock.
     */
    protected function routeProduct(): ?Product {
        return null;
    }

    /**
     * The category-listing route's category, or null. Only the CategoryController overrides this
     * (returning the FWK-resolved route category); other controllers keep the null default so
     * `attachCategory` is a no-op. Null in the editor/docker preview → category widgets mock.
     */
    protected function routeCategory(): ?Category {
        return null;
    }

    /** The unified product LIST attached to `page.products`. CategoryController returns the category
     *  listing; other controllers keep null so `attachProducts` is a no-op. Lets the productList
     *  widget read one variable on any route. */
    protected function routeProducts(): ?ElementCollection {
        return null;
    }

    /** Product-related lists keyed by pId, attached to `page.productRelated`. Only ProductController
     *  overrides this (fetching each block by pId); other controllers keep the empty default so
     *  `attachProductRelated` is a no-op.
     *
     * @return array<string, mixed> */
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
     * @return array<int, mixed>
     */
    protected function buildProductRelated(int $entityId, object $service, bool $categoryProducts = false): array {
        if ($entityId <= 0 || !method_exists($service, 'getRelatedItems')) {
            return [];
        }
        if (!$this->hasWidgetType($this->pages?->getItems() ?? [], 'productRelated')) {
            return [];
        }
        $params = new RelatedItemsParametersGroup();
        if ($categoryProducts) {
            $params->setCategoryProducts(true);
        }
        try {
            $related = $service->getRelatedItems($entityId, '', $params);
        } catch (\Throwable) {
            return [];
        }
        if (!$related instanceof ElementCollection) {
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
        return null;
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
        return [];
    }

    /**
     * The product-detail route's option labels/constants (Sí/No, upload labels, date pattern, missing
     * image), attached to every widget page as `page.bundleLabels`. Only ProductController overrides
     * this; other controllers keep the [] default so `attachBundleLabels` is a no-op.
     *
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @return array<string, mixed>
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
     * @return array<string, mixed>
     */
    protected function routeCommentForm(): array {
        return [];
    }

    protected function isCacheable(): bool {
        if (MagicfrontUtils::isCanvasMode()) {
            return false;
        }
        return parent::isCacheable();
    }
}
