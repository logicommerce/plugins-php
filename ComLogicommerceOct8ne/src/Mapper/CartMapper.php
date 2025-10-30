<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Basket\CartDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Basket\CartItemDTO;

/**
 * This is the class CartMapper to map the cart data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see CartDTO
 * @see CartItemDTO
 */
class CartMapper extends BaseMapper {

    private ?Object $basket;

    private int $totalItems = 0;

    public function __construct($basket) {
        $this->basket = $basket;
    }

    /**
     * Map the cart data
     * 
     * @return object
     */
    public function map(): object {
        $cart = $this->basket;
        $cartItems = $this->getCartItems($cart);
        $cartDTO = new CartDTO();
        $cartDTO->setPrice($this->roundPrice($cart->getTotals()->getSubTotalRows()));
        $cartDTO->setFinalPrice($this->roundPrice($cart->getTotals()->getTotal()));
        $cartDTO->setTotalItems($this->totalItems);
        $cartDTO->setCart($cartItems);
        $cartDTO->setCurrency($this->getCurrencyCode($cart));
        return $cartDTO;
    }

    private function getCartItems($cart): array {
        $cartItems = [];
        foreach($cart->getItems() as $cartItem) {
            $item = new CartItemDTO();
            $item->setInternalId($cartItem->getId());
            $item->setTitle($cartItem->getName());
            $item->setQty($cartItem->getQuantity());
            $item->setPrice($this->roundPrice($cartItem->getTotal()));
            $this->totalItems = $this->totalItems + $cartItem->getQuantity();
            $cartItems[] = $item;
        }
        return $cartItems;
    }

    private function getCurrencyCode($cart): string {
        $basketUser = $cart->getBasketUser();
        return $basketUser->getUser()->getCurrencyCode();
    }
}