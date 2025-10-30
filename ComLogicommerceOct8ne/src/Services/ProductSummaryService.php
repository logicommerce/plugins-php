<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\ProductService;
use FWK\Core\Resources\Loader;
use Plugins\ComLogicommerceOct8ne\Mapper\ProductSummaryMapper;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/** 
 *  This is the class ProductSummayService to get the summary of a list of products
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see ProductSummaryMapper
 * @see PluginRouteParameters
 * @see ProductService
 * @see ErrorMapper
 */
class ProductSummaryService extends BaseService {

    protected ?ProductService $productService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
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
            $message = "productIds must be a list numbers";
            return $this->getErrorMapper($message);
        }
        return $this->getProductsSummary($productIds);
    }

    private function getProductsSummary($productIds): array {
        $this->productService = Loader::service(Services::PRODUCT);
        $products = $this->productService->getProductsByIdList($productIds);
        $productsSummayDTO = new ProductSummaryMapper($products->getItems(), $this->pluginProperties);
        return $productsSummayDTO->map();
    }
}