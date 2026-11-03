<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\ProductService;
use FWK\Core\Resources\Loader;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;
use Plugins\ComLogicommerceOct8ne\Mapper\SearchMapper;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/**
 * This is the class SearchService - Service to get products by search
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see SearchMapper
 * @see PluginRouteParameters
 * @see ProductService
 * @see ProductsParametersGroup
 */
class SearchService extends BaseService {

    protected ?ProductService $productService = null;

    private array $filterParams = [];

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /* 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        $query = $this->getRequestParam(PluginRouteParameters::PARAMETER_SEARCH, '');
        $perPage = $this->getRequestParam(PluginRouteParameters::PARAMETER_PAGE_SIZE, 10);
        $page = $this->getRequestParam(PluginRouteParameters::PARAMETER_PAGE, 1);
        $orderBy = $this->getRequestParam(PluginRouteParameters::PARAMETER_ORDER_BY, 'relevance');
        $direction = $this->getRequestParam(PluginRouteParameters::PARAMETER_DIR, 'asc');
        $brandList = $this->getRequestParam(PluginRouteParameters::PARAMETER_BRANDS_LIST, '');
        $categoryIdList = $this->getRequestParam(PluginRouteParameters::PARAMETER_CATEGORY_ID_LIST, '');
        $filterCustomTag = $this->getRequestParam(PluginRouteParameters::PARAMETER_FILTER_CUSTOM_TAGS, '');
        $this->filterParams($brandList, $categoryIdList, $filterCustomTag);
        $sort = $this->getRelevance($orderBy, $direction);
        return $this->getProductsSearch($query, $perPage, $page, $sort);
    }

    private function filterParams($brandList='', $categoryIdList='', $filterCustomTag='') {
        $this->filterParams["brandList"] = $brandList;
        $this->filterParams["categoryIdList"] = $categoryIdList;
        $this->filterParams["filterCustomTag"] = $filterCustomTag;
    }

    /* 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getProductsSearch($query = '', $perPage = 10, $page = 1, $sort='priority.asc'): object {
        if ($query == '') {
            $searchMapper = new SearchMapper(null, $this->pluginProperties);
            return $searchMapper->map();
        }
        $perPage = $this->getProductLimit($perPage);
        $this->productService = Loader::service(Services::PRODUCT);
        $productsParametersGroup = new ProductsParametersGroup();
        $productsParametersGroup->setQ($query);
        $productsParametersGroup->setPerPage($perPage);
        $productsParametersGroup->setPage($page);
        $productsParametersGroup->setSort($sort);
        $this->addFilters($productsParametersGroup);
        $products = $this->productService->getProducts($productsParametersGroup);
        $searchMapper = new SearchMapper($products, $this->pluginProperties);
        $searchMapper->addAppliedFilter($this->filterParams["brandList"][0]);
        $searchMapper->addAppliedFilter($this->filterParams["categoryIdList"][0]);
        $searchMapper->addAppliedFilter($this->filterParams["filterCustomTag"]);
        return $searchMapper->map();
    }

    private function addFilters(ProductsParametersGroup $productsParametersGroup) {
        if ($this->filterParams["brandList"] != '') {
            $productsParametersGroup->setBrandsList($this->filterParams["brandList"][0]);
        }
        if ($this->filterParams["categoryIdList"] != '') {
            $productsParametersGroup->setCategoryIdList($this->filterParams["categoryIdList"][0]);
        }
        if ($this->filterParams["filterCustomTag"] != '') {
            foreach ($this->filterParams["filterCustomTag"] as $key => $filterCustomTag) {
                $productsParametersGroup->addFilterCustomTag($key, $filterCustomTag[0]);
            }
        }
    }

    private function getRelevance($orderBy, $direction) {
        $sort = $this->parseRelevence($orderBy);
        return $sort . '.' . $direction;    
    }

    private function parseRelevence($orderBy) {
        if ($orderBy === 'relevance') {
            return 'priority';
        } else if ($orderBy === 'price') {
            return 'price';
        } else if ($orderBy === 'name') {
            return 'name';
        }
        return 'prority';
    }
}