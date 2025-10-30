<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\basket;

/**
 * This is the cart item DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\basket
 * 
 * @see \JsonSerializable
 */
class CartItemDTO implements \JsonSerializable {

    private ?string $internalId = null;

    private string $title = '';

    private int $qty = 0;

    private float $price = 0;

    public function __construct() {
    }

    function setInternalId(string $internalId) {
        $this->internalId = $internalId;
    }

    function getInternalId(): string {
        return $this->internalId;
    }

    function setTitle(string $title) {
        $this->title = $title;
    }

    function getTitle(): string {
        return $this->title;
    }

    function setQty(int $qty) {
        $this->qty = $qty;
    }

    function getQty(): int {
        return $this->qty;
    }

    function setPrice(float $price) {
        $this->price = $price;
    }

    function getPrice(): float {
        return $this->price;
    }

    /**
     * Serialize the cart item DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'internalId' => $this->internalId,
            'title' => $this->title,
            'qty' => $this->qty,
            'price' => $this->price
        ];
    }
}