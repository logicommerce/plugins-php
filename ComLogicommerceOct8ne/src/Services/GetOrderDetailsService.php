<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\OrderService;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Response;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;
use Plugins\ComLogicommerceOct8ne\Mapper\OrderDetailsMapper;

/**
 * This is the class GetOrderDetailsService to get the details of an order
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see OrderDetailsMapper
 * @see ErrorMapper
 * @see PluginRouteParameters
 * @see OrderService
 */
class GetOrderDetailsService extends BaseService {

    protected ?OrderService $orderService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /* 
     * Process the request
     * 
     * @return array
     */
    public function process(): mixed {
        if (!$this->validateToken()) {
            Response::forbidden();
        }
        $id = $this->getRequestParam(PluginRouteParameters::PARAMETER_REFERENCE, '');
        if (empty($id)) {
            $message = $this->getErrorMessage(PluginRouteParameters::PARAMETER_REFERENCE);
            return $this->getErrorMapper($message);
        }
        if (!is_numeric($id)) {
            $message = "reference must be a number";
            return $this->getErrorMapper($message);
        }
        return $this->getOrder($id);
    }

    /* 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getOrder($id): mixed {
        $this->orderService = Loader::service(Services::ORDER);
        $order = $this->orderService->getOrder($id);
        if (!is_null($order->getError())) {
            $message = "Order not found";
            return $this->getErrorMapper($message);
        }
        $orderMapper = new OrderDetailsMapper($order);
        return $orderMapper->map();
    }
}