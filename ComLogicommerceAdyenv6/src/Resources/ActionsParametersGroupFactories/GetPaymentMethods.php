<?php

namespace Plugins\ComLogicommerceAdyenv6\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommerceAdyenv6\Enums\PluginDataActions;
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
 * @package Plugins\ComLogicommerceAdyenv6\Resources\ActionsParametersGroupFactories
 */

class GetPaymentMethods implements PluginActionSelectPaymentSystemInterface {

    private string $action = PluginDataActions::GET_PAYMENT_METHODS;
    private array $data = [];

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct(Basket $basket) {

        $selectedBillingAddress = null;

        if($basket->getBasketUser() == null || $basket->getBasketUser()->getUser() == null) {
            $this->data = [
                "country_id" => 'ES',
                "amount" => 0,
                "email" => ''
            ];
            return;
        }
        foreach($basket->getBasketUser()->getUser()->getBillingAddresses() as $billingAddres){
            if($billingAddres->getId() == $basket->getBasketUser()->getUser()->getSelectedBillingAddressId()){
                $selectedBillingAddress = $billingAddres;
		        break;
            }
        }        
        if($selectedBillingAddress == null || $selectedBillingAddress->getLocation() == null){
            $country_id = "ES";
        }
        else {
            $country_id = $selectedBillingAddress->getLocation()->getGeographicalZone()->getCountryCode();
        }
        $this->data = [
            "country_id" => $country_id,
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