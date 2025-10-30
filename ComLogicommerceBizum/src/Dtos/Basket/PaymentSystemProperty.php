<?php

namespace Plugins\ComLogicommerceBizum\Dtos\Basket;

use SDK\Dtos\Basket\PaymentSystemProperty as BasketPaymentSystemProperty;

/**
 * This is the Bizum's PaymentSystemProperty  class.
 * The ComLogicommerceBizum information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @package Plugins\ComLogicommerceBizum\Dtos\Basket
 */
class PaymentSystemProperty extends BasketPaymentSystemProperty {

    protected function setValues(array $values): void {
        $this->values = new PaymentSystemPropertyValues($values);
    }

}
