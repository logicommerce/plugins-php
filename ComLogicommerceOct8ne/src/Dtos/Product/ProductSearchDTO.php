<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

use Plugins\ComLogicommerceOct8ne\Dtos\Filter\FilterInfoDTO;

/**
 * This is the product search DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 * 
 * @see \JsonSerializable
 */
class ProductSearchDTO implements \JsonSerializable {

    private int $total = 0;

    private array $results = [];

    private ?FilterInfoDTO $filters = null;

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

    public function getFilters(): ?FilterInfoDTO {
        return $this->filters;
    }

    public function setFilters(?FilterInfoDTO $filters): void {
        $this->filters = $filters;
    }

    /**
     * Serialize the product search DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'total' => $this->total,
            'results' => $this->results,
            'filters' => $this->filters
        ];
    }
}