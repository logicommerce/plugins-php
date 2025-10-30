<?php

namespace Plugins\ComLogicommerceSprinque\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Sprinque's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @see BasketPaymentSystem
 * 
 * @see PaymentSystem::setPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::getPluginPropertiesConnectorItemProperties()
 * 
 * @see PaymentSystem::getPluginActions()
 * @see PaymentSystem::setPluginActions()
 * 
 * @package Plugins\ComLogicommerceSprinque\Dtos\Basket
 */
class PaymentSystem extends BasketPaymentSystem {

    protected array $pluginPropertiesConnectorItemProperties = [];

    protected array $pluginActions = [];

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PaymentSystemProperty::class);
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
