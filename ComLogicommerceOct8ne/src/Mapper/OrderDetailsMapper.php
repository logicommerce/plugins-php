<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Order\OrderDetailsDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Order\OrderItemDTO;

/**
 * This is the class OrderDetailsMapper to map the order details data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see OrderDetailsDTO
 * @see OrderItemDTO
 */
class OrderDetailsMapper extends BaseMapper {

    private object $order;

    public function __construct($order) {
        $this->order = $order;
    }

    /**
     * Map the order details data
     * 
     * @return object
     */
    public function map(): mixed {
        $order = $this->order;
        $orderDetailsDTO = new OrderDetailsDTO();
        $orderDetailsDTO->setReference($order->getId());
        $orderDetailsDTO->setCurrency($this->getCurrency($order));
        $orderDetailsDTO->setTotal($this->formatCurrency($order->getTotals()->getTotal()));
        $orderDetailsDTO->setDate($this->formatDateTime($order->getDate()));
        $orderDetailsDTO->setLabelState($this->getLabelState($order));
        $orderDetailsDTO->setDeliveryDate($this->convertDateTime($order->getDeliveryDate()));
        $orderItemDTOs = [];
        foreach($order->getItems() as $item) {
            $name = $item->getName();
            $quantity = $item->getQuantity();
            $orderItemDTO = new OrderItemDTO($name, $quantity);
            $orderItemDTOs[] = $orderItemDTO;
        }
        $orderDetailsDTO->setProducts($orderItemDTOs);
        $this->setShipping($order, $orderDetailsDTO);
        return $orderDetailsDTO;
    }

    private function getLabelState($order) {
        $status = $order->getStatus();
        $subStatus = $order->getSubStatus();
        if (!is_null($subStatus) && $subStatus != '') {
            return $status . ' - ' . $subStatus;
        }
        return $status;
    }

    private function setShipping($order, $orderDetailsDTO) {
        foreach($order->getDelivery()->getShipments() as $shipment) {
            $orderDetailsDTO->setCarrier($shipment->getShipping()->getName());
            $orderDetailsDTO->setTrackingUrl($shipment->getTrackingUrl());
            $orderDetailsDTO->setTrackingNumber($shipment->getTrackingNumber());
        }
    }

    private function getCurrency($order): string {
        $currency = null;
        if ($order->getCurrencies() != null) {
            foreach($order->getCurrencies() as $currency) {
                if ($currency->getMode() == 'PURCHASE') {
                    return $currency->getCode();
                }
            }            
        }
        return 'EUR';
    }
}