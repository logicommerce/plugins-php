<?php

namespace Plugins\ComLogicommerceAmazonpay\Dtos\Basket;

use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the Adyen's PaymentSystemAdditionalData  class.
 * The PaymentSystemAdditionalData information will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @see Element
 * @see ElementTrait
 * 
 * @see PaymentSystemAdditionalData::getPaymentData()   
 * @see PaymentSystemAdditionalData::setPaymentData()  
 * 
 * @package Plugins\ComLogicommerceAmazonpay\Dtos\Basket
 */
class PaymentSystemAdditionalData extends Element {
    use ElementTrait;

    protected string $paymentData = "";

    /**
     * Returns the paymentData value.
     *
     * @return string
     */
    public function getPaymentData(): string {
        return $this->paymentData;
    }

    /**
     * Set the additional paymentData value.
     *
     * @param array $paymentData
     *            array with the payment additional data.
     * 
     * @return string
     */
    public function setPaymentData(array $paymentData) {        
        $this->paymentData = json_encode($paymentData);
    }
}