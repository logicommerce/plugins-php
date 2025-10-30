<?php

namespace Plugins\ComLogicommerceRedsysinsite\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystem as BasketPaymentSystem;

/**
 * This is the Redsys's PaymentSystem class.
 * The PaymentSystem information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceRedsysinsite\Dtos\Basket
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
