<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductRelatedDTO;

/**
 * This is the class ProductRelatedMapper to map the related product data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 */
class ProductRelatedMapper extends BaseMapper {

    private array $products;

    public function __construct($products) {
        $this->products = $products;
    }

    /**
     * Map the related product data
     * 
     * @return object
     */
    public function map(): mixed {
        $products = $this->products;
        $productSearchDTO = new ProductRelatedDTO(count($products));
        $productDTOs = [];
        foreach ($products as $product) {
            $productSummaryDTO = $this->mapProductSummary($product);
            $this->parsePrices($productSummaryDTO, $product);
            $productDTOs[] = $productSummaryDTO;
        }
        $productSearchDTO->setResults($productDTOs);
        return $productSearchDTO;
    }

    private function parsePrices($productSummaryDTO, $product) {
        $prices = $this->getPrices($product);
        $price = $prices['price'];
        $basePrice = $prices['basePrice'];
        $productSummaryDTO->setFormattedPrice($this->formatCurrency($price));
        $productSummaryDTO->setFormattedPrevPrice($this->formatCurrency($basePrice));
    }
}