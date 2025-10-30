<?php

namespace Plugins\ComLogicommerceSprinque\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Sprinque's PaymentSystemProperty  class.
 * The ComLogicommerceSprinque information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceSprinque\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
