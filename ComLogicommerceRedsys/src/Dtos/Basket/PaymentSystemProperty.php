<?php

namespace Plugins\ComLogicommerceRedsys\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Redsys's PaymentSystemProperty  class.
 * The ComLogicommerceRedsys information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceRedsys\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
