<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use FWK\Controllers\CategoryController as FWKCategoryController;
use FWK\Core\Controllers\Controller;
use FWK\Core\FilterInput\FilterInputHandler;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Services\CategoryService;
use FWK\Services\ProductService;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Catalog\Category;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;

/**
 * Plugin override for the storefront category route (RouteType::CATEGORY).
 *
 * MagicFront flow (Motor A), NOT the native LC design-assets engine: the category
 * listing page is a SINGLETON MagicFront page (pId 'mff_CATEGORY') whose widget blob
 * is the template painted for EVERY category — exactly how ProductController paints the
 * 'mff_PRODUCT' singleton for every product. FWK's base resolves the route Category into
 * `controllerItem`; we:
 *   1. fetch the category's product LIST and its subcategories;
 *   2. expose the resolved Category via {@see routeCategory()}, its subcategories via
 *      {@see routeSubcategories()} and its product list via {@see routeProducts()} — MagicfrontTrait
 *      attaches them (PageRelationResolver::attachCategory / attachProducts) so category widgets read
 *      `page.category` (raw SDK Category), `page.categories` (subcategories) and `page.products`
 *      (raw SDK Product list). NO custom Twig function, NO contract shape.
 *
 * The 'mff_CATEGORY' template page whose blob is rendered is resolved generically by
 * {@see MagicfrontTrait::magicfrontPage()} (memoized, so setBatchData reuses it to size `perPage`);
 * `controllerItem` stays the route Category (FWK view helpers need it).
 *
 * Deliberately does NOT use FiltrableProductListTrait (its getFilterParams() collides with
 * MagicfrontTrait's) nor RendersDesignAssetsTrait (Motor B). Product filters / sort / pagination
 * are a follow-up; this fetches the plain category product list.
 *
 * Editor/docker preview (no route category): `page.category`/`page.products` are null → each
 * widget renders its inline mock.
 *
 * @see FWKCategoryController
 * @see \Plugins\ComLogicommerceMagicfront\Controllers\Product\ProductController
 * @see \Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver::attachCategory()
 */
class CategoryController extends FWKCategoryController {
    use MagicfrontTrait;

    /** Data key holding the FWK-resolved route Category, read back by routeCategory(). */
    private const MFF_CATEGORY = 'mffCategory';

    private const KEY_PRODUCTS = 'mffCategoryProducts';

    private const KEY_SUBCATS = 'mffSubcategories';

    private ?int $categoryId = null;

    private ?ProductService $productService = null;

    private ?CategoryService $categoryService = null;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->categoryId = $this->getRoute()->getId();
        $this->productService = Loader::service(Services::PRODUCT);
        $this->categoryService = Loader::service(Services::CATEGORY);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        $templatePage = $this->magicfrontPage();
        $this->categoryService->addGetCategoriesByParentId($requests, self::KEY_SUBCATS, $this->categoryId);
        $productParams = new ProductsParametersGroup();
        $productParams->setCategoryId($this->categoryId);
        $productParams->setIncludeSubcategories(true);
        $productParams->setShowFilters(true);
        $perPage = $templatePage instanceof Page ? $this->listingPerPage($templatePage) : null;
        if ($perPage !== null) {
            $productParams->setPerPage($perPage);
        }
        if (!$this->editorMode) {
            $this->applyFilterParams($productParams);
        }
        $this->productService->addGetProducts($requests, self::KEY_PRODUCTS, $productParams);
        $this->setMagicfrontBatchData($requests);
    }

    /**
     * Reads the native LogiCommerce product-filter query params and maps each to its SDK setter/adder.
     * Field-name contract (values arrive as PHP arrays via the widget's `[]` names):
     *   filterCustomTag_<id>[]                → addFilterCustomTag(id, value)
     *   filterCustomTagInterval_<id> "min;max"→ addFilterCustomTagInterval(id, min, max)
     *   filterCustomTagRangeInterval_<id>     → addFilterCustomTagRangeInterval(id, intervalId)
     *   filterOption_<name>[]                 → addFilterOption(name, value)
     *   priceRange "min;max"                  → setFromPrice + setToPrice
     *   brandsList[]                          → setBrandsList(csv)
     *   sort | orderBy                        → setSort
     */
    private function applyFilterParams(ProductsParametersGroup $params): void {
        $pageNumber = (int) $this->getRequestParam(Parameters::PAGE, false, '0');
        if ($pageNumber > 1) {
            $params->setPage($pageNumber);
        }
        $sort = $_GET[Parameters::SORT] ?? $_GET['orderBy'] ?? null;
        if (is_string($sort) && $sort !== '') {
            $params->setSort($sort);
        }
        $brands = $_GET[Parameters::BRANDS_LIST] ?? null;
        if (is_array($brands) && $brands !== []) {
            $params->setBrandsList(implode(',', array_map('intval', $brands)));
        }
        $categories = $_GET['category'] ?? null;
        if (is_array($categories) && $categories !== []) {
            $params->setCategoryIdList(implode(',', array_map('intval', $categories)));
        }
        $priceRange = $_GET[Parameters::PRICE_RANGE] ?? null;
        if (is_string($priceRange) && str_contains($priceRange, ';')) {
            [$from, $to] = array_pad(explode(';', $priceRange, 2), 2, '');
            if (is_numeric($from) && is_numeric($to)) {
                $params->setFromPrice((float) min($from, $to));
                $params->setToPrice((float) max($from, $to));
            }
        }
        $this->applyDynamicFilterParams($params);
    }

    /**
     * Maps the dynamic (id/name-suffixed) filter query params to their SDK adder, mirroring
     * fwk's {@see FilterInputHandler::getAvailableDinamicParam()} split idiom: each key is
     * `<paramName><delimiter><identifier>`, so it splits on the first delimiter and dispatches
     * on the {@see Parameters} prefix. Non-dynamic keys (no delimiter) are ignored here.
     */
    private function applyDynamicFilterParams(ProductsParametersGroup $params): void {
        foreach ($_GET as $key => $value) {
            $parts = explode(FilterInputHandler::DYNAMIC_NAME_PARAM_DELIMITER, (string) $key, 2);
            if (count($parts) !== 2 || $parts[1] === '') {
                continue;
            }
            [$name, $identifier] = $parts;
            switch ($name) {
                case Parameters::FILTER_CUSTOMTAG_INTERVAL:
                    if (ctype_digit($identifier) && is_string($value) && str_contains($value, ';')) {
                        [$from, $to] = array_pad(explode(';', $value, 2), 2, '');
                        $params->addFilterCustomTagInterval((int) $identifier, (string) min($from, $to), (string) max($from, $to));
                    }
                    break;
                case Parameters::FILTER_CUSTOMTAG_RANGE_INTERVAL:
                    if (ctype_digit($identifier)) {
                        foreach ((array) $value as $intervalId) {
                            if ($intervalId !== '') {
                                $params->addFilterCustomTagRangeInterval((int) $identifier, (string) $intervalId);
                            }
                        }
                    }
                    break;
                case Parameters::FILTER_CUSTOMTAG:
                    if (ctype_digit($identifier)) {
                        foreach ((array) $value as $tagValue) {
                            if ($tagValue !== '') {
                                $params->addFilterCustomTag((int) $identifier, (string) $tagValue);
                            }
                        }
                    }
                    break;
                case Parameters::FILTER_OPTION:
                    foreach ((array) $value as $optionValue) {
                        if ($optionValue !== '') {
                            $params->addFilterOption(rawurldecode($identifier), (string) $optionValue);
                        }
                    }
                    break;
            }
        }
    }

    protected function setData(array $additionalData = []): void {
        $this->setDataValue(self::MFF_CATEGORY, $this->getControllerData(Controller::CONTROLLER_ITEM));
        $this->setMagicfrontData();
    }

    /** The route's category (raw SDK Category), attached to every widget page as `page.category`. */
    protected function routeCategory(): ?Category {
        $category = $this->getControllerData(self::MFF_CATEGORY);
        return $category instanceof Category ? $category : null;
    }

    /** The category's product LIST, attached to `page.products` (unified productList source). */
    protected function routeProducts(): ?ElementCollection {
        $products = $this->getControllerData(self::KEY_PRODUCTS);
        return $products instanceof ElementCollection ? $products : null;
    }

    /** The category's child categories, attached to `page.categories` of the subcategoryGrid widget. */
    protected function routeSubcategories(): ?ElementCollection {
        $subcategories = $this->getControllerData(self::KEY_SUBCATS);
        return $subcategories instanceof ElementCollection ? $subcategories : null;
    }

    /** The route category's related PRODUCTS as a flat list → `page.productRelated`, read by the
     *  productRelated widget (symmetric with the product page). `categoryProducts=true` is required so
     *  the SDK returns the category's related products. Delegates to
     *  {@see MagicfrontTrait::buildProductRelated()} (pId-based block filtering temporarily disabled). */
    protected function routeProductRelated(): array {
        return $this->buildProductRelated($this->getRoute()->getId(), Loader::service(Services::CATEGORY), true);
    }
}
