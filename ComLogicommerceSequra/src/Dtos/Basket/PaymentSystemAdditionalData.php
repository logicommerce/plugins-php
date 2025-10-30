<?php

namespace Plugins\ComLogicommerceSequra\Dtos\Basket;

use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the Sequra's PaymentSystemAdditionalData  class.
 * The PaymentSystemAdditionalData information will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @package Plugins\ComLogicommerceSequra\Dtos\Basket
 */
class PaymentSystemAdditionalData extends Element {
    use ElementTrait;

    protected bool $saveToken = false;

    /**
     * Returns the saveToken value.
     *
     * @return bool
     */
    public function getSaveToken(): bool {
        return $this->saveToken;
    }

}
