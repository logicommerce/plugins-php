<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use Plugins\ComLogicommerceOct8ne\Mapper\AdapterInfoMapper;

/**
 * This is the class GetAdapterInfoService to get the adapter information
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see AdapterInfoMapper
 */
class GetAdapterInfoService extends BaseService {

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        return $this->getAdapterInfo();
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return true;
    }

    private function getAdapterInfo(): object {
        $pluginRoute = $this->getPlugin();
        $adapterInfo = new AdapterInfoMapper($pluginRoute);
        return $adapterInfo->map();
    }
}