<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\ProductService;
use FWK\Core\Resources\Loader;
use SDK\Enums\RelatedItemsType;
use SDK\Services\Parameters\Groups\RelatedItemsParametersGroup;
use Plugins\ComLogicommerceOct8ne\Mapper\ProductRelatedMapper;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/** 
 *  This is the class ProductRelatedService to get the related products of a product
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see ProductRelatedMapper
 * @see PluginRouteParameters
 * @see ProductService
 * @see ErrorMapper
 */
class ProductRelatedService extends BaseService {

    protected ?ProductService $productService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        $productId = $this->getRequestParam(PluginRouteParameters::PARAMETER_PRODUCT_ID, '');
        if (empty($productId)) {
            $message = $this->getErrorMessage(PluginRouteParameters::PARAMETER_PRODUCT_ID);
            return $this->getErrorMapper($message);
        }
        return $this->getProductRelated($productId);
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getProductRelated($productId): object {
        $this->productService = Loader::service(Services::PRODUCT);
        $relatedItemsParametersGroup = new RelatedItemsParametersGroup();
        $relatedItemsParametersGroup->setPositionList('relatedProductItems');
        $items = $this->productService->getRelatedItems($productId, RelatedItemsType::PRODUCTS, null);
        $products = [];
        foreach ($items as $item) {
            $products = array_merge($products, $item->getProducts());
        }
        $productRelatedDTO = new ProductRelatedMapper($products);
        return $productRelatedDTO->map();
    }
}