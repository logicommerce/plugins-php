<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Product;

use FWK\Controllers\Product\ProductController as FWKProductController;
use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\RoutePaths;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Utils;
use FWK\Enums\LanguageLabels;
use FWK\Enums\RouteTypes\InternalProduct;
use FWK\Enums\Services;
use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use FWK\ViewHelpers\Product\ProductJsonData;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BundleOptionsResolver;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the storefront product route (RouteType::PRODUCT).
 *
 * MagicFront flow (Motor A): the product detail page is a SINGLETON MagicFront page
 * (pId 'mff_PRODUCT') whose widget blob is the template painted for EVERY product.
 * FWK's base ProductController keeps the real Product in `controllerItem` (so its SEO,
 * ViewHelper and product macros stay intact); the 'mff_PRODUCT' template page whose blob is
 * rendered is resolved generically by {@see MagicfrontTrait::magicfrontPage()}. The route Product
 * is exposed via {@see routeProduct()} so MagicfrontTrait attaches it to every widget page
 * (PageRelationResolver::attachProduct) as `page.product`.
 *
 * When no 'mff_PRODUCT' page exists magicfrontPage() is null and the trait renders nothing
 * (the plugin only owns this route when PRODUCT is in `availablepages`, or in preview via mfToken).
 *
 * @see FWKProductController
 * @see \Plugins\ComLogicommerceMagicfront\Controllers\HomeController
 * @see \Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver::attachProduct()
 */
class ProductController extends FWKProductController {
    use MagicfrontTrait;

    private const BUNDLE_DEFINITIONS_KEY = 'mffProductBundleDefinitions';


    private const COMMENTS_KEY = 'mffProductComments';

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->addBundleDefinitions($requests);
        $this->addComments($requests);
        $this->setMagicfrontBatchData($requests);
    }

    private function addBundleDefinitions(BatchRequests $requests): void {
        if (!self::getTheme()->getConfiguration()->getCommerce()->getShowBundles()) {
            return;
        }
        Loader::service(Services::PRODUCT)->addGetBundleDefinitions($requests, self::BUNDLE_DEFINITIONS_KEY, $this->getRoute()->getId());
    }


    private function addComments(BatchRequests $requests): void {
        Loader::service(Services::PRODUCT)->addGetComments($requests, self::COMMENTS_KEY, $this->getRoute()->getId());
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setMagicfrontData();
    }

    protected function routeProduct(): ?Product {
        $product = $this->getControllerData(Controller::CONTROLLER_ITEM);
        return $product instanceof Product ? $product : null;
    }

    protected function routeProductBundles(): ?BundleDefinitionsWithGroupings {
        $definitions = $this->getControllerData(self::BUNDLE_DEFINITIONS_KEY);
        if (!$definitions instanceof ElementCollection) {
            return null;
        }
        return new BundleDefinitionsWithGroupings($this->getRoute()->getId(), $definitions->getItems());
    }

    /** The route product's related PRODUCTS as a flat list → `page.productRelated`, read by the
     *  productRelated widget. Delegates to {@see MagicfrontTrait::buildProductRelated()} with the
     *  Product service (pId-based block filtering temporarily disabled). */
    protected function routeProductRelated(): array {
        return $this->buildProductRelated($this->getRoute()->getId(), Loader::service(Services::PRODUCT));
    }

    /** The route product's comments, attached to every widget page as `page.comments`. */
    protected function routeComments(): array {
        $comments = $this->getControllerData(self::COMMENTS_KEY);
        return $comments instanceof ElementCollection ? $comments->getItems() : [];
    }

    protected function routeBundleLabels(): array {
        if (!$this->routeProduct() instanceof Product) {
            return [];
        }
        $weekStart = $this->commerceCalendar ? $this->commerceCalendar->getFirstDayOfWeek() - 1 : 0;
        return BundleOptionsResolver::build($this->getLanguageSheet(), $this->getRoute(), $weekStart);
    }

    protected function routeProductJson(): array {
        $product = $this->routeProduct();
        if (!$product instanceof Product) {
            return [];
        }
        return (new ProductJsonData($product))->output();
    }

    protected function routeWishlist(): array {
        $product = $this->routeProduct();
        if (!$product instanceof Product) {
            return [];
        }
        $sheet = $this->getLanguageSheet();
        $wishlist = Session::getInstance()->getAggregateData()->getWishlist();
        return [
            'isLogged'    => Utils::isSessionLoggedIn(),
            'ids'         => $wishlist ? $wishlist->getItemIdList() : [],
            'labelAdd'    => $sheet[LanguageLabels::ADD_TO_WISHLIST] ?? '',
            'labelDelete' => $sheet[LanguageLabels::DELETE_FROM_WISHLIST] ?? '',
        ];
    }

    protected function routeCommentForm(): array {
        if (!$this->routeProduct() instanceof Product) {
            return [];
        }
        $comments = self::getTheme()->getConfiguration()->getForms()->getComments();
        $anonymousRating = $comments && $comments->getAnonymousRatingEnabled();
        return [
            'action'      => RoutePaths::getPath(InternalProduct::ADD_COMMENT),
            'canComment'  => $anonymousRating || Utils::isSessionLoggedIn(),
        ];
    }

}
