<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Dtos\ElementCollection as DtosElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Core\Services\Parameters\Groups\ParametersGroup;
use SDK\Dtos\Catalog\Category;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;

/**
 * Enriches the widget tree with related data (products, categories) by
 * collecting batch requests per-page and applying the results back onto
 * the tree.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class PageRelationResolver {

    public const LC_PREFIX = 'lc-';

    public const PAGE_ID = 'page-id-';

    public const PAGES = 'pages';

    public static function setData(?ElementCollection $data): ?ElementCollection {
        if ($data === null) {
            return null;
        }
        $pages = $data;
        $batchRequests = new BatchRequests();
        self::prepareBatchRequestsRecursive($pages, $batchRequests);
        $batchResults = BatchService::getInstance()->send($batchRequests);
        self::applyBatchResultsRecursive($batchResults, $pages);
        return $pages;
    }

    /**
     * Attach the route's product-detail product to EVERY widget page (recursively into
     * subpages/slots) so product-detail widgets read the raw SDK Product as `page.product` —
     * the singular analogue of the category-driven `page.products` LIST. No-op when there is
     * no product (non-product route, or editor/docker preview → widgets show their mock).
     *
     * Run AFTER {@see setData()} so the tree is already normalised to plugin {@see Page} DTOs.
     */
    public static function attachProduct(?ElementCollection $pages, ?Product $product): void {
        if ($pages === null || $product === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setProduct($product);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachProduct(new ElementCollection(['items' => $subItems]), $product);
            }
        }
    }

    /**
     * Attach the route's category (raw SDK Category) to EVERY widget page as `page.category`
     * (the singular analogue of `page.categories` subcategory lists), and the category's product
     * LIST to `page.products` — but ONLY on widgets that do NOT declare their own `categoryId`
     * (those keep the list {@see setData} already resolved for them, e.g. featured*). No-op when
     * there is no category (non-category route / editor preview → widgets show their mock).
     *
     * Run AFTER {@see setData()} so the tree is already normalised to plugin {@see Page} DTOs.
     */
    public static function attachCategory(?ElementCollection $pages, ?Category $category, ?ElementCollection $subcategories = null): void {
        if ($pages === null || $category === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setCategory($category);
            if ($subcategories !== null) {
                $ownCategoryId = $page->getModuleSettings()['categoryId'] ?? null;
                $hasOwnCategoryId = $ownCategoryId !== null && $ownCategoryId !== '' && (int) $ownCategoryId !== 0;
                if (!$hasOwnCategoryId) {
                    $page->setCategories($subcategories);
                }
            }
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachCategory(new ElementCollection(['items' => $subItems]), $category, $subcategories);
            }
        }
    }

    /**
     * Attach a flat product LIST to every widget page as `page.products` (skipping widgets that
     * declare their own `categoryId`, which get their list via the batch mechanism). This is the
     * unified source for the productList widget: category routes pass the category listing, product
     * routes pass the related-products list, so the widget reads one variable regardless of route.
     */
    public static function attachProducts(?ElementCollection $pages, ?ElementCollection $products): void {
        if ($pages === null || $products === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $ownCategoryId = $page->getModuleSettings()['categoryId'] ?? null;
            $hasOwnCategoryId = $ownCategoryId !== null && $ownCategoryId !== '' && (int) $ownCategoryId !== 0;
            if (!$hasOwnCategoryId) {
                $page->setProducts($products);
            }
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachProducts(new ElementCollection(['items' => $subItems]), $products);
            }
        }
    }

    public static function attachBreadcrumb(?ElementCollection $pages, array $breadcrumb): void {
        if ($pages === null || empty($breadcrumb)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setBreadcrumb($breadcrumb);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachBreadcrumb(new ElementCollection(['items' => $subItems]), $breadcrumb);
            }
        }
    }

    public static function attachAccount(?ElementCollection $pages, array $account): void {
        if ($pages === null || empty($account)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setAccount($account);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachAccount(new ElementCollection(['items' => $subItems]), $account);
            }
        }
    }

    public static function attachBundleLabels(?ElementCollection $pages, array $bundleLabels): void {
        if ($pages === null || empty($bundleLabels)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setBundleLabels($bundleLabels);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachBundleLabels(new ElementCollection(['items' => $subItems]), $bundleLabels);
            }
        }
    }

    public static function attachProductJson(?ElementCollection $pages, array $productJson): void {
        if ($pages === null || empty($productJson)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setProductJson($productJson);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachProductJson(new ElementCollection(['items' => $subItems]), $productJson);
            }
        }
    }

    public static function attachWishlist(?ElementCollection $pages, array $wishlist): void {
        if ($pages === null || empty($wishlist)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setWishlist($wishlist);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachWishlist(new ElementCollection(['items' => $subItems]), $wishlist);
            }
        }
    }

    public static function attachCommentForm(?ElementCollection $pages, array $commentForm): void {
        if ($pages === null || empty($commentForm)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setCommentForm($commentForm);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachCommentForm(new ElementCollection(['items' => $subItems]), $commentForm);
            }
        }
    }

    /**
     * Attach account-widget data onto EVERY widget page (recursively into subpages) by calling the given
     * Page setters. The single generic path behind every account data widget — {@see DataWidgetRegistry}
     * builds the `setterName => value` map per widget type (e.g. orders → setOrders + setOrdersPagination),
     * so the full-page render and the `widgetContent` AJAX endpoint share one recursion.
     *
     * @param array $assignments setterName => value to apply on each page
     */
    public static function attachWidgetData(?ElementCollection $pages, array $assignments): void {
        if ($pages === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            foreach ($assignments as $setter => $value) {
                $page->$setter($value);
            }
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachWidgetData(new ElementCollection(['items' => $subItems]), $assignments);
            }
        }
    }

    public static function attachComments(?ElementCollection $pages, array $comments): void {
        if ($pages === null || empty($comments)) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setComments($comments);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachComments(new ElementCollection(['items' => $subItems]), $comments);
            }
        }
    }

    /**
     * Attach the product-related lists keyed by their (platform-opaque) pId as `page.productRelated`.
     * The controller owns the keys because the API response carries no pId — it fetches each related
     * block by pId (LogiCommerce: positionList name) and keys the map by that pId. Lets several
     * productList instances on one page each render a different block via their `relatedId` setting.
     *
     * @param array $productRelated
     */
    public static function attachProductRelated(?ElementCollection $pages, array $productRelated): void {
        if ($pages === null || $productRelated === []) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setProductRelated($productRelated);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachProductRelated(new ElementCollection(['items' => $subItems]), $productRelated);
            }
        }
    }

    public static function attachProductBundles(?ElementCollection $pages, ?BundleDefinitionsWithGroupings $productBundles): void {
        if ($pages === null || $productBundles === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $page->setProductBundles($productBundles);
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                self::attachProductBundles(new ElementCollection(['items' => $subItems]), $productBundles);
            }
        }
    }

    protected static function prepareBatchRequestsRecursive(ElementCollection &$pages, BatchRequests $batchRequests): void {
        $pages = DtosElementCollection::fillFromParentCollection($pages, Page::class);
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            self::addProductsGridBatchRequests($page, $batchRequests);
            self::addCatalogBoundProductsBatchRequests($page, $batchRequests);

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $subItems]);
                self::prepareBatchRequestsRecursive($subPages, $batchRequests);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function applyBatchResultsRecursive(array $batchResults, ?ElementCollection $pages): void {
        if ($pages === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $base = self::getBatchKey($page);
            if (isset($batchResults[$base . '_products'])) {
                $page->setProducts($batchResults[$base . '_products']);
            }
            if (isset($batchResults[$base . '_categories'])) {
                $page->setCategories($batchResults[$base . '_categories']);
            }

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $subItems]);
                self::applyBatchResultsRecursive($batchResults, $subPages);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function addProductsGridBatchRequests(Page $page, BatchRequests $batchRequests): void {
        if ($page->getCustomType() !== 'productsGrid') {
            return;
        }
        $productService = Loader::service(Services::PRODUCT);
        self::addBatchRequest(
            new ProductsParametersGroup(),
            $page->getModuleSettings(),
            self::LC_PREFIX,
            [$productService, 'addGetProducts'],
            self::getBatchKey($page) . '_products',
            $batchRequests
        );
    }

    /**
     * Convention-driven catalog-bound product fetch. ANY Magicfront widget
     * that exposes a non-zero `categoryId` in its moduleSettings opts into
     * product resolution — no per-widget handler required. The result lands
     * on `$page->setProducts(...)` and the widget Twig reads it as
     * `page.products`.
     *
     * Mapping (widget property → ProductsParametersGroup):
     *   categoryId       → categoryId            (required, non-zero)
     *   productCount     → perPage               (defaults to 1 when absent,
     *                                             so single-product widgets
     *                                             like featuredProduct work
     *                                             without declaring it)
     *   (always)         → includeSubcategories=true  (parent-category
     *                                                  selections list
     *                                                  descendants' products)
     *
     * Adding a new catalog-driven widget requires ZERO code changes here —
     * just declare `categoryId` (and optionally `productCount`) in the
     * widget JSON. The widget's Twig consumes `page.products.items` directly,
     * either looping it (productList) or indexing items[0] (featuredProduct).
     */
    protected static function addCatalogBoundProductsBatchRequests(Page $page, BatchRequests $batchRequests): void {
        $moduleSettings = $page->getModuleSettings();
        $categoryId = $moduleSettings['categoryId'] ?? null;
        if ($categoryId === null || $categoryId === '' || (int) $categoryId === 0) {
            return;
        }
        $settings = [
            'categoryId'           => $categoryId,
            'includeSubcategories' => true,
        ];
        if (isset($moduleSettings['productCount']) && (int) $moduleSettings['productCount'] > 0) {
            $settings['perPage'] = (int) $moduleSettings['productCount'];
        }
        $productService = Loader::service(Services::PRODUCT);
        self::addBatchRequest(
            new ProductsParametersGroup(),
            $settings,
            '',
            [$productService, 'addGetProducts'],
            self::getBatchKey($page) . '_products',
            $batchRequests
        );
    }

    protected static function addBatchRequest(
        ParametersGroup $group,
        array $settings,
        string $prefix,
        callable $serviceAddMethod,
        string $batchKey,
        BatchRequests $batchRequests
    ): void {
        $group = self::buildParametersGroup($group, $settings, $prefix);
        // Skip if the settings did not populate any filter on top of the defaults.
        if (count($group->toArray()) === count((new (get_class($group))())->toArray())) {
            return;
        }
        $serviceAddMethod($batchRequests, $batchKey, $group);
    }

    /**
     * Populate a parameters group from a `$prefix{propertyName}` keyed
     * settings array, coercing scalar values to each property's declared type.
     */
    protected static function buildParametersGroup(ParametersGroup $group, array $settings, string $prefix): ParametersGroup {
        foreach ((new \ReflectionClass($group))->getProperties() as $prop) {
            $name   = $prop->getName();
            $setter = 'set' . ucfirst($name);
            $key    = $prefix . $name;

            if (!array_key_exists($key, $settings) || !method_exists($group, $setter)) {
                continue;
            }

            $value = self::coerceToPropertyType($settings[$key], $prop->getType());
            if ($value === null && $prop->getType() !== null) {
                continue;
            }

            // Category 0 is the "no category" sentinel used by the editor.
            if ($name === 'categoryId' && (int) $value === 0) {
                continue;
            }

            $group->$setter($value);
        }
        return $group;
    }

    /**
     * Coerce `$value` to match the declared property type. Returns null when
     * the value can't safely be converted (the caller should then skip it).
     */
    private static function coerceToPropertyType(mixed $value, ?\ReflectionType $type): mixed {
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }
        $typeName = $type->getName();
        return match ($typeName) {
            'int', 'float' => is_numeric($value) ? self::castScalar($value, $typeName) : null,
            'bool'         => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'string'       => is_string($value) ? $value : null,
            default        => $value,
        };
    }

    private static function castScalar(mixed $value, string $typeName): mixed {
        settype($value, $typeName);
        return $value;
    }

    protected static function getBatchKey(Page $page): string {
        return self::PAGE_ID . ($page->getId() === 0 ? $page->getDraftId() : (string) $page->getId());
    }
}
