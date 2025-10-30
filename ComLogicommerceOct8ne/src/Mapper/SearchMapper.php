<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductSearchDTO;
use Plugins\ComLogicommerceOct8ne\Mapper\FilterMapper;
use SDK\Core\Dtos\PluginProperties;

/**
 * This is the class SearchMapper to map the search data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see ProductSearchDTO
 */
class SearchMapper extends BaseMapper {

    private ?Object $items;

    private ?string $appliedFilter;

    public function __construct($items, ?PluginProperties $pluginProperties = null) {
        $this->items = $items;
        parent::__construct($pluginProperties);
    }

    /**
     * Map the search data
     * 
     * @return object
     */
    public function map(): mixed {
        if (is_null($this->items)) {
            $productSearchDTO = new ProductSearchDTO(0);
            $productSearchDTO->setResults([]);
            return $productSearchDTO;
        }
        $products = $this->items->getItems();
        $pagination = $this->items->getPagination();
        $productSearchDTO = new ProductSearchDTO($pagination->getTotalItems());
        $productDTOs = [];
        foreach ($products as $product) {
            $productDTOs[] = $this->mapProductSummary($product);
        }
        $productSearchDTO->setResults($productDTOs);
        $filter = new FilterMapper($this->items->getFilter());
        $filter->setAppliedFilter($this->getAppliedFilter());
        $productSearchDTO->setFilters($filter->map());
        return $productSearchDTO;
    }

    public function setAppliedFilter($appliedFilter) {
        $this->appliedFilter = $appliedFilter;
    }

    private function getAppliedFilter() {
        return $this->appliedFilter;
    }
}