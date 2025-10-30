<?php

namespace Plugins\ComLogicommercePaycomet\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Paycomet's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystem
 * 
 * @see PaymentSystem::setPluginPropertiesConnectorItemProperties()
 * @see PaymentSystem::getPluginPropertiesConnectorItemProperties()
 * 
 * @package Plugins\ComLogicommercePaycomet\Dtos\Basket
 */
class PaymentSystem extends BasketPaymentSystem {

    protected array $pluginPropertiesConnectorItemProperties = [];

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
}