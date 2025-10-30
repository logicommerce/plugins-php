<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

/**
 * This is the product related DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 * 
 * @see \JsonSerializable
 */
class ProductRelatedDTO implements \JsonSerializable {

    private int $total = 0;

    private array $results = [];

    public function __construct(int $total) {
        $this->total = $total;
    }

    public function addProduct(ProductBaseDTO $product): void {
        $this->results[] = $product;
    }

    public function getTotal(): int {
        return $this->total;
    }

    public function getResults(): array {
        return $this->results;
    }

    public function setTotal(int $total): void {
        $this->total = $total;
    }

    public function setResults(array $results): void {
        $this->results = $results;
    }

    /**
     * Serialize the product related DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'total' => $this->total,
            'results' => $this->results
        ];
    }
}