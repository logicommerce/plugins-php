<?php

namespace Plugins\ComLogicommerceSprinque\Dtos\Basket;

use SDK\Core\Dtos\PaymentSystemPropertyValues as CoreDtosPaymentSystemPropertyValues;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Resources\Date;

/**
 * This is the Sprinque's PaymentSystemPropertyValues  class.
 * The ComLogicommerceSprinque information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see ComLogicommerceSprinque::getName()
 * @see ComLogicommerceSprinque::getIdentifier()
 * @see ComLogicommerceSprinque::getCreditStatus()
 * @see ComLogicommerceSprinque::getCreditLimit()
 * @see ComLogicommerceSprinque::getAvaliableCreditLimit()
 *
 * @see ElementTrait
 * 
 * @package Plugins\ComLogicommerceSprinque\Dtos\Basket
 */
class PaymentSystemPropertyValues extends CoreDtosPaymentSystemPropertyValues {
    use ElementTrait;

    protected string $name = '';

    protected string $identifier = '';

    protected string $creditStatus = '';

    protected string $creditLimit = '';

    protected string $availableCreditLimit = '';

    /**
     * Returns the name value.
     *
     * @return string
     */
    public function getName(): string {
        return $this->name;
    }

    /**
     * Returns the indetifier value.
     *
     * @return string
     */
    public function getIdentifier(): string {
        return $this->identifier;
    }

    /**
     * Returns the credit status value.
     *
     * @return string
     */
    public function getCreditStatus(): string {
        return $this->creditStatus;
    }

    /**
     * Returns the credit limit value.
     *
     * @return Date|NULL
     */
    public function getCreditLimit(): string {
        return $this->creditLimit;
    }

    /**
     * Returns the avaliable credit limit value.
     *
     * @return Date|NULL
     */
    public function getAvailableCreditLimit(): string {
        return $this->availableCreditLimit;
    }

}
