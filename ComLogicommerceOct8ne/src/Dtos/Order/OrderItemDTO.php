<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Order;

/**
 * This is the order item DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Order
 * 
 * @see \JsonSerializable
 */
class OrderItemDTO implements \JsonSerializable {

    private string $name = "";

    private int $quantity = 0;

    public function __construct(string $name, int $quantity) {
        $this->name = $name;
        $this->quantity = $quantity;
    }

    function setName(string $name) {
        $this->name = $name;
    }

    function getName(): string {
        return $this->name;
    }

    function setQuantity(int $quantity) {
        $this->quantity = $quantity;
    }

    function getQuantity(): int {
        return $this->quantity;
    }

    /**
     * Serialize the order item DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'name' => $this->name,
            'quantity' => $this->quantity
        ];
    }

}