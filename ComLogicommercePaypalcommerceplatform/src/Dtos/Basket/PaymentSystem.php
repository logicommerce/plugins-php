<?php

namespace Plugins\ComLogicommercePaypalcommerceplatform\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Redsys's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommercePaypalcommerceplatform\Dtos\Basket
 */
class PaymentSystem extends BasketPaymentSystem {

    protected array $pluginPropertiesConnectorItemProperties = [];

    protected array $pluginActions = [];

    protected array $pluginProperties = [];

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PaymentSystemProperty::class);
    }

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
     * Returns the data of getPaymentMethods.
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
