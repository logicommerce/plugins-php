<?php

namespace Plugins\ComLogicommerceAdyen\Dtos\Basket;

use SDK\Core\Dtos\PaymentSystemPropertyValues as CoreDtosPaymentSystemPropertyValues;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Resources\Date;

/**
 * This is the Adyen's PaymentSystemPropertyValues  class.
 * The ComLogicommerceAdyen information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PaymentSystemPropertyValues::getIdentifier()
 * @see PaymentSystemPropertyValues::getCardNumber()
 * @see PaymentSystemPropertyValues::getExpiryDate()
 *
 * @see ElementTrait
 * @see PaymentSystemPropertyValues
 * 
 * @package Plugins\ComLogicommerceAdyen\Dtos\Basket
 */
class PaymentSystemPropertyValues extends CoreDtosPaymentSystemPropertyValues {
    use ElementTrait;

    protected string $identifier = '';

    protected string $cardNumber = '';

    protected string $expiryDate = '';

    /**
     * Returns the indetifier value.
     *
     * @return string
     */
    public function getIdentifier(): string {
        return $this->identifier;
    }

    /**
     * Returns the card number value.
     *
     * @return string
     */
    public function getCardNumber(): string {
        return $this->cardNumber;
    }

    /**
     * Returns the expiraty Date value.
     *
     * @return Date|NULL
     */
    public function getExpiryDate(): ?string {
        return $this->expiryDate;
    }

    protected function setExpiryDate(string $expiryDate): void {
        $this->expiryDate = $expiryDate;
    }
}
