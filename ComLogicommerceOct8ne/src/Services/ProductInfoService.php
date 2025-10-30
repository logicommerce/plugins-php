<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\ProductService;
use FWK\Core\Resources\Loader;
use Plugins\ComLogicommerceOct8ne\Mapper\ProductMapper;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/** 
 * This is the class ProductInfo for get the product information
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see ProductMapper
 * @see ErrorMapper
 * @see PluginRouteParameters
 * @see ProductService
 */
class ProductInfoService extends BaseService {

    protected ?ProductService $productService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Process the request
     * 
     * @return array
     */
    public function process(): mixed {
        $productIds = $this->getRequestParam(PluginRouteParameters::PARAMETER_PRODUCT_IDS, '');
        if (empty($productIds)) {
            $message = $this->getErrorMessage(PluginRouteParameters::PARAMETER_PRODUCT_IDS);
            return $this->getErrorMapper($message);
        }
        if (!$this->checkIds($productIds)) {
            $message = "productIds must be a list of numbers";
            return $this->getErrorMapper($message);
        }
        return $this->getProducts($productIds);
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getProducts($productIds): array {
        $this->productService = Loader::service(Services::PRODUCT);
        $products = $this->productService->getProductsByIdList($productIds);
        $productsDTO = new ProductMapper($products->getItems(), $this->pluginProperties);
        return $productsDTO->map();
    }
}