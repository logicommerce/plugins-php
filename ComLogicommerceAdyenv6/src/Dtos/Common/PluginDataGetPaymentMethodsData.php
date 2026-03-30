<?php

namespace Plugins\ComLogicommerceAdyenv6\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin payment methods data class.
 * The API plugin payment methods data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetPaymentMethodsData::getPaymentMethods()
 * @see PluginDataGetPaymentMethodsData::getOneClickPaymentMethods()
 * @see PluginDataGetPaymentMethodsData::getStoredPaymentMethods()
 * @see PluginDataGetPaymentMethodsData::getRecurringDetailReference()
 * @see PluginDataGetPaymentMethodsData::getStatus()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceAdyenv6\Dtos\Common
 */
class PluginDataGetPaymentMethodsData {
    use ElementTrait;

    protected string $paymentMethods = '';
    protected string $oneClickPaymentMethods = '';
    protected string $storedPaymentMethods = '';
    protected array $recurringDetailReference = [];

    protected int $status;

    /**
     * Returns the paymentMethods.
     *
     * @return string
     */
    public function getPaymentMethods(): string {
        return $this->paymentMethods;
    }

    protected function setPaymentMethods(string $paymentMethods){
        $this->paymentMethods = $paymentMethods;
    }
    
    /**
     * Returns the oneClickPaymentMethods.
     *
     * @return string
     */
    public function getOneClickPaymentMethods(): string {
        return $this->oneClickPaymentMethods;
    }

    protected function setOneClickPaymentMethods(string $oneClickPaymentMethods){
        $this->oneClickPaymentMethods = $oneClickPaymentMethods;
    }
    
    /**
     * Returns the storedPaymentMethods.
     *
     * @return string
     */
    public function getStoredPaymentMethods(): string {
        return $this->storedPaymentMethods;
    }

    protected function setStoredPaymentMethods(string $storedPaymentMethods){
        $this->storedPaymentMethods = $storedPaymentMethods;
    }

    /**
     * Returns the RecurringDetailReference.
     *
     * @return array
     */
    public function getRecurringDetailReference(): array {
        return $this->recurringDetailReference;
    }

    protected function setRecurringDetailReference(array $recurringDetailReference){
        $this->recurringDetailReference = $recurringDetailReference;
    }   

    /**
     * Returns the property status.
     *
     * @return int
     */
    public function getStatus(): int {
        return $this->status;
    }
}