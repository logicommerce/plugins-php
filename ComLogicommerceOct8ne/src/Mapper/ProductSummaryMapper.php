<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use SDK\Core\Dtos\PluginProperties;

/**
 * This is the class ProductSummaryMapper to map the product summary data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 */
class ProductSummaryMapper extends BaseMapper {

    private array $products = [];

    public function __construct(array $products, ?PluginProperties $pluginProperties = null) {
        $this->products = $products;
        parent::__construct($pluginProperties);
    }

    /**
     * Map the product summary data
     * 
     * @return array
     */
    public function map(): array {
        $response = [];
        foreach ($this->products as $product) {
            $productDTO = $this->mapProductSummary($product);
            $response[] = $productDTO;
        }
        return $response;
    }

}