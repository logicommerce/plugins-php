<?php

namespace Plugins\ComLogicommerceSequra\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Sequra's PaymentSystemProperty  class.
 * The ComLogicommerceSequra information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceSequra\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
