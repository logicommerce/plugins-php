<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Order;

/**
 * This is the order details DTO class.
 *
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Order
 * 
 * @see \JsonSerializable 
 */
class OrderDetailsDTO extends OrderDTO {

    private string $carrier;

    private string $trackingNumber;

    private string $trackingUrl;

    private array $products;

    public function getCarrier() {
        return $this->carrier;
    }

    public function setCarrier($carrier) {
        $this->carrier = $carrier;
    }

    public function getTrackingNumber() {
        return $this->trackingNumber;
    }

    public function setTrackingNumber($trackingNumber) {
        $this->trackingNumber = $trackingNumber;
    }

    public function getTrackingUrl() {
        return $this->trackingUrl;
    }

    public function setTrackingUrl($trackingUrl) {
        $this->trackingUrl = $trackingUrl;
    }

    public function getProducts() {
        return $this->products;
    }

    public function setProducts($products) {
        $this->products = $products;
    }

    public function addProduct($product) {
        $this->products[] = $product;
    }

    /**
     * Serialize the order details DTO
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
            'deliveryDate' => $this->deliveryDate,
            'carrier' => $this->carrier,
            'trackingNumber' => $this->trackingNumber,
            'trackingUrl' => $this->trackingUrl,
            'products' => $this->products
        ];
    }
}