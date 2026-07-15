<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Product;

use FWK\Controllers\Product\ProductController as FWKProductController;
use FWK\Core\Form\FormFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use FWK\Services\PageService;
use FWK\Services\ProductService;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\RendersDesignAssetsTrait;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Enums\SortableEnum;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Enums\CommentsSort;
use SDK\Enums\RecommendItemType;
use SDK\Services\Parameters\Groups\Product\CommentsParametersGroup;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;
use SDK\Services\Parameters\Groups\RelatedItemsParametersGroup;

/**
 * Plugin override for the storefront product route (RouteType::PRODUCT).
 *
 * Extends the FRAMEWORK ProductController (NOT the store's SITE one) so the
 * plugin never depends on commerce code. Replicates the data the SITE
 * controller assembles (pages, related items, comments, bundles, reward points,
 * discounts, forms, random products) using only FWK + SDK APIs, then layers the
 * design override via RendersDesignAssetsTrait. No DCS widget rendering.
 *
 * @see FWKProductController
 */
class ProductController extends FWKProductController {
    use RendersDesignAssetsTrait;

    private const PAGE_TYPE = 'product';

    /** Page position => twig variable name (SITE LogicommerceSectionPositions). */
    private const PRODUCT_PAGES = [
        60 => 'productTopPages',
        62 => 'productBottomPages',
    ];

    /** SITE\Enums\LogicommerceSectionPositions::RELATED_PRODUCTS. */
    private const RELATED_PRODUCTS_POSITION = 9;

    private const FORM_CONTACT = 'formProductContact';
    private const FORM_COMMENT = 'formProductComments';
    private const FORM_PRODUCT_RECOMMEND = 'formProductRecommend';
    private const FORM_BUNDLE_RECOMMEND = 'formBundleRecommend';
    private const FORM_STOCK_ALERT = 'formProductStockAlert';

    private const RELATED_PRODUCT_ITEMS = 'relatedProductItems';

    private ?int $productId = null;
    private ?ProductService $productService = null;
    private ?PageService $pageService = null;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->productId = $this->getRoute()->getId();
        $this->productService = Loader::service(Services::PRODUCT);
        $this->pageService = Loader::service(Services::PAGE);
    }

    protected function setBatchData(BatchRequests $requests): void {
        $commerce = self::getTheme()->getConfiguration()->getCommerce();

        $this->pageService->addGetPagesByPositionList($requests, 'productPages', join(',', array_keys(self::PRODUCT_PAGES)));
        $this->productService->addGetProductRichSnippets($requests, 'productRichSnippets', $this->productId);

        $relatedItemsParametersGroup = new RelatedItemsParametersGroup();
        $relatedItemsParametersGroup->setPositionList((string) self::RELATED_PRODUCTS_POSITION);
        $this->productService->addGetRelatedItems($requests, 'relatedItems', $this->productId, '', $relatedItemsParametersGroup);

        $commentsParametersGroup = new CommentsParametersGroup();
        $commentsParametersGroup->setSort(CommentsSort::DATEADDED . '.' . SortableEnum::SORT_DIRECTION_DESC);
        $this->productService->addGetComments($requests, 'productComments', $this->productId, $commentsParametersGroup);

        if ($commerce->getShowBundles()) {
            $this->productService->addGetBundleDefinitions($requests, 'productBundleDefinitions', $this->productId);
        }
        if ($commerce->getShowRewardPoints()) {
            $this->productService->addGetProductRewardPoints($requests, 'productRewardPoints', $this->productId);
        }
        if ($commerce->getShowDiscounts()) {
            $this->productService->addGetProductDiscounts($requests, 'productDiscounts', $this->productId);
        }
    }

    protected function setData(array $additionalData = []): void {
        $commerce = self::getTheme()->getConfiguration()->getCommerce();

        $this->splitPages();
        $this->setForms($commerce->getShowStockAlert());
        $this->splitRelatedItems();
        $this->setRandomProducts();

        if ($commerce->getShowBundles()) {
            $this->setDataValue('productBundles', new BundleDefinitionsWithGroupings($this->productId, $this->getControllerData('productBundleDefinitions')->getItems()));
        }

        // MagicFront design override: swap the design key to switch designs.
        $this->registerDesignAssets(self::PAGE_TYPE, 'design197');
    }

    /** Split pages by self::PRODUCT_PAGES position. */
    private function splitPages(): void {
        $productPages = $this->getControllerData('productPages');
        if ($productPages !== null && $productPages instanceof ElementCollection) {
            $productPages = $productPages->getItems();
            foreach (self::PRODUCT_PAGES as $position => $valueName) {
                $this->setDataValue($valueName, array_filter($productPages, fn ($page) => $page->getPosition() === $position));
            }
        }
    }

    /** Set product form data values. */
    private function setForms(bool $showStockAlert): void {
        $this->setDataValue(self::FORM_CONTACT, FormFactory::getProductContact($this->productId));
        $this->setDataValue(self::FORM_PRODUCT_RECOMMEND, FormFactory::getRecommend($this->productId, RecommendItemType::PRODUCT));
        $this->setDataValue(self::FORM_BUNDLE_RECOMMEND, FormFactory::getRecommend(0, RecommendItemType::BUNDLE));
        $this->setDataValue(self::FORM_COMMENT, FormFactory::getComment($this->productId));

        if ($showStockAlert) {
            $this->setDataValue(self::FORM_STOCK_ALERT, FormFactory::getStockAlert());
        }
    }

    /** Split related products section out of 'relatedItems' and drop the raw key. */
    private function splitRelatedItems(): void {
        $relatedItems = $this->getControllerData('relatedItems');
        if ($relatedItems !== null && $relatedItems instanceof ElementCollection) {
            $relatedItemsItems = $relatedItems->getItems();
            $this->setDataValue(self::RELATED_PRODUCT_ITEMS, array_values(array_filter(
                $relatedItemsItems,
                fn ($section) => $section->getPosition() === self::RELATED_PRODUCTS_POSITION
            )));
        }
        $this->deleteControllerData('relatedItems');
    }

    /** Fall back to random products from the same category when no related products exist. */
    private function setRandomProducts(): void {
        $relatedProducts = $this->getControllerData(self::RELATED_PRODUCT_ITEMS);
        if (!empty($relatedProducts) && empty($relatedProducts[0]->getProducts())) {
            $productsParametersGroup = new ProductsParametersGroup();
            $productsParametersGroup->setCategoryId($this->getControllerData(self::CONTROLLER_ITEM)->getMainCategory());
            $productsParametersGroup->setRandomItems(8);
            $this->setDataValue('randomProducts', $this->productService->getProducts($productsParametersGroup));
        }
    }
}
