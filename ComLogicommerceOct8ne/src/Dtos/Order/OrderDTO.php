<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Order;

/**
 * This is the order DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Order
 * 
 * @see \JsonSerializable
 */
class OrderDTO implements \JsonSerializable {

    protected ?string $date = '';

    protected ?string $reference = '';

    protected ?string $total = "0";

    protected ?string $currency = "EUR";

    protected ?string $labelState = '';

    protected ?string $deliveryDate = '';

    public function __construct() {
    }

    function setDate(string $date) {
        $this->date = $date;
    }

    function getDate(): string {
        return $this->date;
    }

    function setReference(string $reference) {
        $this->reference = $reference;
    }

    function getReference(): string {
        return $this->reference;
    }

    function setTotal(string $total) {
        $this->total = $total;
    }

    function getTotal(): string {
        return $this->total;
    }

    function setCurrency(string $currency) {
        $this->currency = $currency;
    }

    function getCurrency(): string {
        return $this->currency;
    }

    function setLabelState(string $labelState) {
        $this->labelState = $labelState;
    }

    function getLabelState(): string {
        return $this->labelState;
    }

    function setDeliveryDate(string $deliveryDate) {
        $this->deliveryDate = $deliveryDate;
    }

    function getDeliveryDate(): string {
        return $this->deliveryDate;
    }

    /**
     * Serialize the order DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'date' => $this->date,
            'reference' => $this->reference,
            'total' => $this->total,
            'currency' => $this->currency,
            'labelState' => $this->labelState,
            'deliveryDate' => $this->deliveryDate
        ];
    }
}