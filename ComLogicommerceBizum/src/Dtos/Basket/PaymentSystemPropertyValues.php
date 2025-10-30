<?php

namespace Plugins\ComLogicommerceBizum\Dtos\Basket;

use SDK\Core\Dtos\PaymentSystemPropertyValues as CoreDtosPaymentSystemPropertyValues;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Resources\Date;

/**
 * This is the Bizum's PaymentSystemPropertyValues  class.
 * The ComLogicommerceBizum information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see ComLogicommerceBizum::getIdentifier()
 * @see ComLogicommerceBizum::getCardNumber()
 * @see ComLogicommerceBizum::getExpiryDate()
 *
 * @see ElementTrait
 * 
 * @package Plugins\ComLogicommerceBizum\Dtos\Basket
 */
class PaymentSystemPropertyValues extends CoreDtosPaymentSystemPropertyValues {
    use ElementTrait;

    protected string $identifier = '';

    protected string $cardNumber = '';

    protected ?Date $expiryDate = null;

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
    public function getExpiryDate(): ?Date {
        return $this->expiryDate;
    }

    protected function setExpiryDate(string $expiryDate): void {
        $this->expiryDate = Date::create($expiryDate);
    }
}
