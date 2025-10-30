<?php

namespace Plugins\ComLogicommerceRedsysinsite\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Redsys's PaymentSystemProperty  class.
 * The ComLogicommerceRedsysinsite information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceRedsysinsite\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
