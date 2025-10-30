<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Common\AdapterInfoDTO;

/**
 * This is the class AdapterInfo
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 */
class AdapterInfoMapper {

    private ?Object $pluginRoute;

    public function __construct($pluginRoute) {
        $this->pluginRoute = $pluginRoute;
    }

    /**
     * Map the adapter info
     * 
     * @return object
     */
    public function map(): object {
        $adapterIntoDTO = new AdapterInfoDTO();
        $adapterIntoDTO->setEnabled($this->getPluginActive());
        return $adapterIntoDTO;
    }

    private function getPluginActive(): bool {
        if (empty($this->pluginRoute)) {
            return false;
        }
        return $this->pluginRoute->isActive();
    }
}