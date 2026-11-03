<?php

namespace Plugins\ComLogicommerceAplazame\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Aplazame's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PaymentSystem::setPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::getPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::setPluginProperties()
 * @see PaymentSystem::getPluginProperties()  
 * 
 * @package Plugins\ComLogicommerceAplazame\Dtos\Basket
 */
class PaymentSystem extends BasketPaymentSystem {

    protected array $pluginProperties = [];

    protected array $pluginPropertiesConnectorItemProperties = [];

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
     * Sets the properties of the plugin connector.
     *
     * @return array
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
}
