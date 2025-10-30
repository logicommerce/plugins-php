<?php

namespace Plugins\ComLogicommerceMultisafepay\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Multisafepay's PaymentSystemProperty  class.
 * The ComLogicommerceMultisafepay information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystemProperty
 * 
 * @package Plugins\ComLogicommerceMultisafepay\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
