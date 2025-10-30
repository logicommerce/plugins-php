<?php

namespace Plugins\ComLogicommerceMultisafepay\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommerceMultisafepay\Enums\PluginDataActions;
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
 * @package Plugins\ComLogicommerceMultisafepay\Resources\ActionsParametersGroupFactories
 */

class GetMerchantApplePay {

    private string $action = PluginDataActions::GET_MERCHANT_APPLE_PAY;

    private array $data = [];

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct($data) {
        $this->data = [
            "origin_domain" => $data['originDomain'],
            "validation_url" => $data['validationUrl'],
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