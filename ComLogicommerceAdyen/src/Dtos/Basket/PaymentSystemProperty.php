<?php

namespace Plugins\ComLogicommerceAdyen\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Adyen's PaymentSystemProperty  class.
 * The ComLogicommerceAdyen information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystemProperty
 * 
 * @package Plugins\ComLogicommerceAdyen\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
