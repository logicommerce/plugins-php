<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Basket;

/**
 * This is the cart DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Basket
 * 
 * @see \JsonSerializable
 */
class CartDTO implements \JsonSerializable {

    private float $price = 0;

    private float $finalPrice = 0;

    private string $currency = 'EUR';

    private int $totalItems = 0;

    private array $cart = [];

    public function __construct() {
    }

    function setPrice(float $price) {
        $this->price = $price;
    }

    function getPrice(): float {
        return $this->price;
    }

    function setFinalPrice(float $finalPrice) {
        $this->finalPrice = $finalPrice;
    }

    function getFinalPrice(): float {
        return $this->finalPrice;
    }

    function setCurrency(string $currency) {
        $this->currency = $currency;
    }

    function getCurrency(): string {
        return $this->currency;
    }

    function setTotalItems(int $totalItems) {
        $this->totalItems = $totalItems;
    }

    function getTotalItems(): int {
        return $this->totalItems;
    }

    function setCart(array $cart) {
        $this->cart = $cart;
    }

    function getCart(): array {
        return $this->cart;
    }

    function addCart($cart) {
        $this->cart[] = $cart;
    }

    function removeCart($cart) {
        $index = array_search($cart, $this->cart);
        if ($index !== false) {
            unset($this->cart[$index]);
        }
    }

    function clearCart() {
        $this->cart = [];
    }

    /**
     * Serialize the cart DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'price' => $this->price,
            'finalPrice' => $this->finalPrice,
            'currency' => $this->currency,
            'totalItems' => $this->totalItems,
            'cart' => $this->cart
        ];
    }

}