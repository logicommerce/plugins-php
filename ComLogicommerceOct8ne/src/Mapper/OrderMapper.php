<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use FWK\Enums\Services;
use FWK\Services\OrderService;
use FWK\Core\Resources\Loader;
use Plugins\ComLogicommerceOct8ne\Dtos\Order\OrderDTO;

/**
 * This is the class OrderMapper
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see OrderDTO
 */
class OrderMapper extends BaseMapper {

    private array $orders;

    protected ?OrderService $orderService = null;

    public function __construct($orders) {
        $this->orders = $orders;
    }

    /**
     * Map the order data
     * 
     * @return array
     */
    public function map(): array {
        $this->orderService = Loader::service(Services::ORDER);
        $orders = $this->orders;
        $orderDTOs = [];
        foreach($orders as $order) {
            $orderDTO = new OrderDTO();
            $orderDTO->setReference($order->getId());
            $orderDTO->setDate($this->formatDateTime($order->getDate()));
            $orderDTO->setTotal($this->formatCurrency($order->getTotal()));
            $orderDTO->setLabelState($this->getLabelState($order));
            if ($order->getDeliveryDate() != null) {
                $orderDTO->setDeliveryDate($this->formatDateTime($order->getDeliveryDate()));
            }
            $orderDTOs[] = $orderDTO;
        }
        return $orderDTOs;
    }

    private function getLabelState($order) {
        $status = $order->getStatus();
        $subStatus = $order->getSubStatus();
        if (!is_null($subStatus) && $subStatus != '') {
            return $status . ' - ' . $subStatus;
        }
        return $status;
    }
}