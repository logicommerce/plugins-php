<?php

namespace Plugins\ComLogicommercePaypalcommerceplatform\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommercePaypalcommerceplatform\Enums\PluginDataActions;
use SDK\Core\Interfaces\PluginActionSelectPaymentSystemInterface;
use SDK\Dtos\Basket\Basket;
use SDK\Services\Parameters\Groups\PluginDataParametersGroup;

/**
 * This is the plugin actions parameter factory GetPaymentMethods class.
 *
 * @see GetPaymentMethods::getParametersGroup()
 *
 * @see PluginDataActions
 * @see PluginActionSelectPaymentSystemInterface
 * @see PluginDataParametersGroup
 * @see Basket
 *
 * @package Plugins\ComLogicommercePaypalcommerceplatform\Resources\ActionsParametersGroupFactories
 */

class GetAccessToken implements PluginActionSelectPaymentSystemInterface {

    private string $action = PluginDataActions::GET_ACCESS_TOKEN;

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct(Basket $basket){}

    /**
     * Get parameters for action call
     *     
     * @return PluginDataParametersGroup
     */

    public function getParametersGroup(): PluginDataParametersGroup{
        $params = new PluginDataParametersGroup();
        $params->setAction($this->action);
        return $params;
    }
}