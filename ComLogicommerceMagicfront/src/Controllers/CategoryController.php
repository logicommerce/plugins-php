<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use SDK\Core\Resources\Timer;
use FWK\Core\Resources\Utils;
use FWK\Controllers\CategoryController as FWKCategoryController;
use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use FWK\Services\CategoryService;
use FWK\Services\ProductService;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\CategoryFacets;
use Plugins\ComLogicommerceMagicfront\Core\Resources\ProductListQuery;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Filter\Filter;
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
 * MagicfrontTrait's) nor RendersDesignAssetsTrait (Motor B). The buyer's page, sort and filters
 * come from {@see ProductListQuery}, through the FWK's product-list filters.
 *
 * Editor/docker preview (no route category): `page.category`/`page.products` are null → each
 * widget renders its inline mock.
 *
 * @see FWKCategoryController
 * @see \Plugins\ComLogicommerceMagicfront\Controllers\Product\ProductController
 * @see \Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver::attachCategory()
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers
 */
class CategoryController extends FWKCategoryController {
    use MagicfrontTrait;

    /** Data key holding the FWK-resolved route Category, read back by routeCategory(). */
    private const MFF_CATEGORY = 'mffCategory';

    private const KEY_PRODUCTS = 'mffCategoryProducts';

    private const KEY_SUBCATS = 'mffSubcategories';

    private ?int $categoryId = null;

    /** Whether the request carries a product filter (brand, subcategory, price, custom tag or option); sort and page are not. */
    private bool $buyerFiltered = false;

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
        Utils::addTimerDebugFlag('cat-magicfrontPage', Timer::START_SUFFIX);
        $templatePage = $this->magicfrontPage();
        Utils::addTimerDebugFlag('cat-magicfrontPage', Timer::END_SUFFIX);
        $this->categoryService->addGetCategoriesByParentId($requests, self::KEY_SUBCATS, $this->categoryId);
        $productParams = $this->baseListParams();
        $perPage = $templatePage instanceof Page ? $this->listingPerPage($templatePage) : null;
        if ($perPage !== null) {
            $productParams->setPerPage($perPage);
        }
        if (!RenderMode::isPreviewMode()) {
            $this->buyerFiltered = ProductListQuery::apply($productParams);
        }
        $this->productService->addGetProducts($requests, self::KEY_PRODUCTS, $productParams);
        $this->setMagicfrontBatchData($requests);
    }

    /** The category's product list as the page asks it before any buyer filter: the category with its subcategories, with facets. */
    private function baseListParams(): ProductsParametersGroup {
        $params = new ProductsParametersGroup();
        $params->setCategoryId($this->categoryId);
        $params->setIncludeSubcategories(true);
        $params->setShowFilters(true);
        return $params;
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

    /** The category's product LIST, attached to `page.products` (unified productList source), as LogiCommerce returned it. */
    protected function routeProducts(): ?ElementCollection {
        $products = $this->getControllerData(self::KEY_PRODUCTS);
        return $products instanceof ElementCollection ? $products : null;
    }

    /**
     * The category's own facets → `page.categoryFacets`, only when the buyer's filter found nothing: one more request,
     * after the batch, in that case alone ({@see CategoryFacets}). The facets do not depend on the products, so one is
     * enough.
     */
    protected function routeCategoryFacets(): ?Filter {
        return CategoryFacets::whenEmpty($this->routeProducts(), $this->buyerFiltered, function (): ?ElementCollection {
            $params = $this->baseListParams();
            $params->setPerPage(1);
            return $this->productService->getProducts($params);
        });
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
