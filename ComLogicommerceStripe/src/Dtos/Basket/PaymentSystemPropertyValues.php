<?php

namespace Plugins\ComLogicommerceStripe\Dtos\Basket;

use SDK\Core\Dtos\PaymentSystemPropertyValues as CoreDtosPaymentSystemPropertyValues;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Resources\Date;

/**
 * This is the Stripe's PaymentSystemPropertyValues  class.
 * The ComLogicommerceStripe information will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PaymentSystemPropertyValues::getIdentifier()
 * @see PaymentSystemPropertyValues::getCardNumber()
 * @see PaymentSystemPropertyValues::getExpiryDate()
 *
 * @see ElementTrait
 * @see PaymentSystemPropertyValues
 * 
 * @package Plugins\ComLogicommerceStripe\Dtos\Basket
 */
class PaymentSystemPropertyValues extends CoreDtosPaymentSystemPropertyValues {
    use ElementTrait;

    protected string $intentId = '';

    protected string $clientSecret = '';

    /**
     * Returns the intentId value.
     *
     * @return Date|NULL
     */
    public function getIntentId(): ?string {
        return $this->intentId;
    }

    protected function setIntentId(string $intentId): void {
        $this->intentId = $intentId;
    }

    /**
     * Returns the clientSecret value.
     *
     * @return Date|NULL
     */
    public function getClientSecret(): ?string {
        return $this->clientSecret;
    }

    protected function setClientSecret(string $clientSecret): void {
        $this->clientSecret = $clientSecret;
    }
}
