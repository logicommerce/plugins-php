<?php

namespace Plugins\ComLogicommercePaycomet\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Paycomet's PaymentSystemProperty  class.
 * The ComLogicommercePaycomet information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystemProperty
 * 
 * @package Plugins\ComLogicommercePaycomet\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
