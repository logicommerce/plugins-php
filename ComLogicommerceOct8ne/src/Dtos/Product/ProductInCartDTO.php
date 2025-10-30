<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

/**
 * This is the product in cart DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 * 
 * @see \JsonSerializable
 */
class ProductInCartDTO extends ProductBaseDTO {

    private int $qty;

    public function __construct(int $qty) {
        $this->qty = $qty;
    }

    function setQty(int $qty) {
        $this->qty = $qty;
    }

    function getQty(): int {
        return $this->qty;
    }

    /**
     * Serialize the product in cart DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'internalId' => $this->internalId,
            'title' => $this->title,
            'formattedPrice' => $this->formattedPrice,
            'formattedPrevPrice' => $this->formattedPrevPrice,
            'productUrl' => $this->productUrl,
            'thumbnail' => $this->thumbnail,
            'qty' => $this->qty
        ];
    }
}