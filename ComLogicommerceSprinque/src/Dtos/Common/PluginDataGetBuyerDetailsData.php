<?php

namespace Plugins\ComLogicommerceSprinque\Dtos\Common;

use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * This is the plugin payment methods data class.
 * The API plugin payment methods data will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see PluginDataGetPaymentMethodsData::getBuyerId()
 * @see PluginDataGetPaymentMethodsData::getCredit()
 * @see PluginDataGetPaymentMethodsData::getCreditLimit()
 * @see PluginDataGetPaymentMethodsData::getCreditStatus()
 * @see PluginDataGetPaymentMethodsData::getStatus()
 *
 * @see ElementTrait
 *
 * @package Plugins\ComLogicommerceSprinque\Dtos\Common
 */
class PluginDataGetBuyerDetailsData {
    use ElementTrait;

    protected int $status;
    protected string $buyerId = '';
	protected string $credit = '';
	protected string $creditLimit = '';
	protected string $creditStatus = '';

    /**
     * Returns the buyerId.
     *
     * @return string
     */
    public function getBuyerId(): string {
        return $this->buyerId;
    }

    protected function setBuyerId(string $buyerId) {
        $this->buyerId = $buyerId;
    }

    /**
     * Returns the credit.
     *
     * @return string
     */
    public function getCredit(): string {
        return $this->credit;
    }

    protected function setCredit(string $credit) {
        $this->credit = $credit;
    }

    /**
     * Returns the creditLimit.
     *
     * @return string
     */
    public function getCreditLimit(): string {
        return $this->creditLimit;
    }

    protected function setCreditLimit(string $creditLimit) {
        $this->creditLimit = $creditLimit;
    }

    /**
     * Returns the creditStatus.
     *
     * @return string
     */
    public function getCreditStatus(): string {
        return $this->creditStatus;
    }

    protected function setCreditStatus(string $creditStatus) {
        $this->creditStatus = $creditStatus;
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