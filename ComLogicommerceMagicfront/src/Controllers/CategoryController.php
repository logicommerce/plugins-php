<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use FWK\Controllers\CategoryController as FWKCategoryController;
use FWK\Core\Controllers\FiltrableProductListTrait;
use FWK\Core\Form\FormFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Services\CategoryService;
use FWK\ViewHelpers\Product\ProductViewHelper;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\RendersDesignAssetsTrait;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Product\ProductsDiscountsParametersGroup;

/**
 * Plugin override for the storefront category route (RouteType::CATEGORY).
 *
 * Extends the FRAMEWORK CategoryController (NOT the store's SITE one) so the
 * plugin never depends on commerce code. The product list / filter / sort /
 * template pipeline comes from FWK's FiltrableProductListTrait; the extra
 * filter-module data the storefront's SITE traits provide (isFiltering,
 * filter-module data, commerce flags, product discounts) is replicated here
 * using only FWK + SDK APIs.
 *
 * The category design override (190/191/197/199) is layered via
 * RendersDesignAssetsTrait. No DCS widget rendering (no MagicfrontTrait), which
 * would otherwise override getFilterParams() and drop the product filters.
 *
 * @see FWKCategoryController
 */
class CategoryController extends FWKCategoryController {
    use FiltrableProductListTrait;
    use RendersDesignAssetsTrait;

    /** Page key for this controller. */
    private const PAGE_TYPE = 'category';

    private const PRODUCTS = 'products';
    private const PRODUCTS_DISCOUNTS = 'productsDiscounts';
    private const PRODUCTS_IDS_WITH_DISCOUNTS = 'productsIdsWithDiscounts';
    private const FORM_STOCK_ALERT = 'formProductStockAlert';

    private ?int $categoryId = null;

    private ?CategoryService $categoryService = null;

    public function __construct(Route $route) {
        // Must run BEFORE parent::__construct so requestParams (built there) get
        // the product-filter params from this trait's getFilterParams().
        $this->initFiltrableProductList(self::PRODUCTS, self::getTheme()->getConfiguration()->getCategory()->getProductList());
        parent::__construct($route);
        $this->categoryService = Loader::service(Services::CATEGORY);
        $this->categoryId = $this->getRoute()->getId();
    }

    protected function setBatchData(BatchRequests $requests): void {
        $this->categoryService->addGetCategoriesByParentId($requests, 'subcategories', $this->categoryId);
        $this->categoryService->addGetCategoryRichSnippets($requests, 'categoryRichSnippets', $this->categoryId);
    }

    protected function setData(array $additionalData = []): void {
        $this->addAdditionalRequestParameters([
            Parameters::CATEGORY_ID => $this->categoryId,
            Parameters::INCLUDE_SUBCATEGORIES => $this->getControllerData(self::CONTROLLER_ITEM)->getIncludeSubcategories(),
        ]);
        $this->setProducts();
        $this->setDataValue('filtering', $this->isFiltering());
        $this->setFilterModuleData();

        $commerce = self::getTheme()->getConfiguration()->getCommerce();
        if ($commerce->getShowStockAlert()) {
            $this->setDataValue(self::FORM_STOCK_ALERT, FormFactory::getStockAlert());
        }
        if ($commerce->getShowDiscounts()) {
            $this->setProductsDiscounts($this->getControllerData(self::PRODUCTS)->getItems());
        }

        // MagicFront design override: swap the design key to switch designs.
        $this->registerDesignAssets(self::PAGE_TYPE, 'design191');
        $this->setSelectedTemplate();
    }

    /** Replicates SITE\...\SetFilteringTrait::isFiltering(). */
    private function isFiltering(): bool {
        return ProductViewHelper::getFiltering($this->itemListConfiguration->getApplicableFilters(), $this->productsFilter);
    }

    /**
     * Replicates SITE\...\ProductsFilterModuleDataProviderTrait::setFilterModuleData()
     * for the CATEGORY route. Exposes the data the filter snippets consume.
     */
    private function setFilterModuleData(): void {
        $productsListConf = self::getTheme()->getConfiguration()->getCategory()->getProductList();
        $this->setDataValue('defaultParametersValuesData', $productsListConf->getDefaultParametersValues());
        $this->setDataValue('itemListData', $productsListConf);
        $this->setDataValue('applicableFiltersData', $productsListConf->getApplicableFilters());
        $this->setDataValue('paginationData', $this->getControllerData(self::PRODUCTS)->getPagination());
    }

    /** Replicates SITE\...\ProductsDiscountsModuleDataProviderTrait::setProductsDiscounts(). */
    private function setProductsDiscounts(array $products): void {
        $dataProductsDiscounts = new ElementCollection();
        if (!empty($products)) {
            $productIds = implode(',', array_map(fn($product) => $product->getId(), $products));
            $group = new ProductsDiscountsParametersGroup();
            $group->setProductIdList($productIds);
            $dataProductsDiscounts = Loader::service(Services::PRODUCT)->getProductsDiscounts($group);
        }
        $this->setDataValue(self::PRODUCTS_DISCOUNTS, $dataProductsDiscounts);

        $idsWithDiscounts = explode(',', implode(',', array_map(
            fn($productsDiscount) => implode(',', $productsDiscount->getApplicableProductIds()),
            $dataProductsDiscounts->getItems()
        )));
        $this->setDataValue(self::PRODUCTS_IDS_WITH_DISCOUNTS, $idsWithDiscounts);
    }

    /**
     * Expose the selected grid template (1/2) read straight from the query, so
     * the products grid can switch column count.
     */
    private function setSelectedTemplate(): void {
        $template = filter_input(INPUT_GET, 'template', FILTER_VALIDATE_INT) ?: 1;
        $this->setDataValue('mffTemplate', in_array($template, [1, 2], true) ? $template : 1);
    }
}
