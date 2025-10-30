<?php

namespace Plugins\ComLogicommerceRedsysinsite\Dtos\Basket;

use SDK\Core\Dtos\PaymentSystemPropertyValues as CoreDtosPaymentSystemPropertyValues;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Resources\Date;

/**
 * This is the Redsys's PaymentSystemPropertyValues  class.
 * The ComLogicommerceRedsysinsite information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see ComLogicommerceRedsysinsite::getIdentifier()
 * @see ComLogicommerceRedsysinsite::getCardNumber()
 * @see ComLogicommerceRedsysinsite::getExpiryDate()
 *
 * @see ElementTrait
 * 
 * @package Plugins\ComLogicommerceRedsysinsite\Dtos\Basket
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
