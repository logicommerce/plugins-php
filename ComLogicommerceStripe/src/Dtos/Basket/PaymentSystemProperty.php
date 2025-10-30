<?php

namespace Plugins\ComLogicommerceStripe\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Stripe's PaymentSystemProperty  class.
 * The ComLogicommerceStripe information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see BasketPaymentSystemProperty
 * 
 * @package Plugins\ComLogicommerceStripe\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
