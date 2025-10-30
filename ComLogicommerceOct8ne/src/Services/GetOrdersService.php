<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\UserService;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Response;
use SDK\Services\Parameters\Groups\User\OrderParametersGroup;
use Plugins\ComLogicommerceOct8ne\Mapper\OrderMapper;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/**
 * This is the class GetOrdersService to get the orders of a user
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see OrderMapper
 * @see PluginRouteParameters
 * @see UserService
 */
class GetOrdersService extends BaseService {

    protected ?UserService $userService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        if (!$this->validateToken()) {
            Response::forbidden();
        }
        $page = $this->getRequestParam(PluginRouteParameters::PARAMETER_PAGE, 1);
        $perPage = $this->getRequestParam(PluginRouteParameters::PARAMETER_PAGE_SIZE, 10);
        return $this->getOrders($page, $perPage);
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getOrders($page, $perPage): array {
        $this->userService = Loader::service(Services::USER);
        $orders = [];
        $orderParametersGroup = new OrderParametersGroup();
        $orderParametersGroup->setPerPage($perPage);
        $orderParametersGroup->setPage($page);
        $orders = $this->userService->getOrders($orderParametersGroup)->getItems();
        $orderMapper = new OrderMapper($orders);
        return $orderMapper->map();
    }
    
}