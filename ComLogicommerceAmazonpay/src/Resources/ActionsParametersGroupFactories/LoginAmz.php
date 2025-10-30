<?php

namespace Plugins\ComLogicommerceAmazonpay\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommerceAmazonpay\Enums\PluginDataActions;
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
 * @package Plugins\ComLogicommerceAmazonpay\Resources\ActionsParametersGroupFactories
 */

class LoginAmz {

    private string $action = PluginDataActions::LOGIN_AMZ;
    private array $data = [];

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct() {
        $this->data = [
            "amzLogin" => 'true'
        ];
        return;
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