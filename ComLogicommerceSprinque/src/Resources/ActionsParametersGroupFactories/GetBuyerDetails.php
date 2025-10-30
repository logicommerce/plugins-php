<?php

namespace Plugins\ComLogicommerceSprinque\Resources\ActionsParametersGroupFactories;

use Plugins\ComLogicommerceSprinque\Enums\PluginDataActions;
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
 * @package Plugins\ComLogicommerceSprinque\Resources\ActionsParametersGroupFactories
 */

class GetBuyerDetails implements PluginActionSelectPaymentSystemInterface {

    private string $action = PluginDataActions::GET_BUYER_DETAILS;
    private array $data = [];

    /**
     * Constructor method.
     *
     * @param Basket $basket
     */

    public function __construct(Basket $basket){
        $selectedBillingAddress = null;
        if($basket->getBasketUser() == null || $basket->getBasketUser()->getUser() == null) {
            $this->data = [
                "email" => '',
                "vat" => ''
            ];
            return;
        }
        foreach($basket->getBasketUser()->getUser()->getBillingAddresses() as $billingAddres){
            if($billingAddres->getId() == $basket->getBasketUser()->getUser()->getSelectedBillingAddressId()){
                $selectedBillingAddress = $billingAddres;
		        break;
            }
        }
        if ($selectedBillingAddress != null && $selectedBillingAddress->getVat() != null) {
            $vat = $selectedBillingAddress->getVat();
        }
        else if ($selectedBillingAddress != null && $selectedBillingAddress->getNif() != null) {
            $vat = $selectedBillingAddress->getNif();
        }
        else {
            $vat = "-";
        }
        $this->data = [
            "vat" => $vat,
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