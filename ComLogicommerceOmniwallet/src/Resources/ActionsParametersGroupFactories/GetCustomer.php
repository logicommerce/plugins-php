<?php

namespace Plugins\ComLogicommerceOmniwallet\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommerceOmniwallet\Enums\PluginDataActions;
use SDK\Dtos\Basket\Basket;
use SDK\Services\Parameters\Groups\PluginDataParametersGroup;

/**
 * This is the plugin actions parameter factory GetCustomer class.
 *
 * @see GetCustomer::getParametersGroup()
 *
 * @see PluginDataActions
 * @see PluginDataParametersGroup
 * @see Basket
 *
 * @package Plugins\ComLogicommerceOmniwallet\Resources\ActionsParametersGroupFactories
 */

class GetCustomer {

    private string $action = PluginDataActions::GET_CUSTOMER;
    private array $data = [];

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct(Basket $basket) {

        if($basket->getBasketUser() == null || $basket->getBasketUser()->getUser() == null) {
            $this->data = [
                "amount" => 0,
                "email" => ''
            ];
            return;
        }
        $this->data = [
            "amount" => strval($basket->getTotals()->getTotal()),
            "email" => $basket->getBasketUser()->getUser()->getEmail()
        ];
    }

    /**
     * Get parameters for action call
     *     
     * @return PluginDataParametersGroup
     */

    public function getParametersGroup(): PluginDataParametersGroup{
        $params = new PluginDataParametersGroup();
        $params->setAction($this->action);
        $params->setData($this->data);
        return $params;
    }
}