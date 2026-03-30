<?php

namespace Plugins\ComLogicommercePaypal\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Paypal's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystem
 * 
 * @see PaymentSystem::setPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::getPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::setPluginProperties()
 * @see PaymentSystem::getPluginProperties()  
 * @see PaymentSystem::getPluginActions()
 * @see PaymentSystem::setPluginActions()
 * 
 * @package Plugins\ComLogicommercePaypal\Dtos\Basket
 */
class PaymentSystem extends BasketPaymentSystem {

    protected array $pluginPropertiesConnectorItemProperties = [];

    protected array $pluginProperties = [];

    protected array $pluginActions = [];

    /**
     * Sets the properties of the plugin connector.
     * @param array $pluginPropertiesConnectorItemProperties
     *            array with the plugin connector properties.
     * @return void
     */
    public function setPluginPropertiesConnectorItemProperties(array $pluginPropertiesConnectorItemProperties): void {
        $this->pluginPropertiesConnectorItemProperties = $pluginPropertiesConnectorItemProperties;
    }

    /**
     * Returns the properties of the plugin connector.
     *
     * @return array
     */
    public function getPluginPropertiesConnectorItemProperties(): array {
        return $this->pluginPropertiesConnectorItemProperties;
    }

    /**
     * Sets the properties of the plugin.
     * @param array $pluginProperties
     *            array with the plugin properties.
     *
     * @return array
     */
    public function setPluginProperties(array $pluginProperties): void {
        $this->pluginProperties = $pluginProperties;
    }

    /**
     * Returns the properties of the plugin connector.
     *
     * @return array
     */
    public function getPluginProperties(): array {
        return $this->pluginProperties;
    }

    /**
     * Returns the data of getPluginActions.
     *
     * @return array
     */
    public function getPluginActions(): array {
        return $this->pluginActions;
    }

    /**
     * Sets the plugin actions
     * @param array $pluginActions
     *            array with the plugin actions.
     *
     * @return array
     */
    public function setPluginActions(array $pluginActions): void {
        $this->pluginActions = $pluginActions;
    }
}