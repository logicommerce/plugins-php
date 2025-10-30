<?php

namespace Plugins\ComLogicommerceRedsys\Dtos\Common;

use Plugins\ComLogicommerceRedsys\Dtos\Basket\PaymentSystemPropertyValues;
use SDK\Dtos\Common\UserPluginPaymentToken as CoreUserPluginPaymentToken;

/**
 * This is the user plugin payment token class.
 * The user plugin payment token will be stored in that class and will remain immutable (only get methods are available)
 *
 * @see UserPluginPaymentToken::getData()
 *
 * @see Element
 * @see ElementTrait
 * @see ElementModuleTrait
 *
 * @package Plugins\ComLogicommerceRedsys\Dtos\Common
 */
class UserPluginPaymentToken extends CoreUserPluginPaymentToken {

    protected ?PaymentSystemPropertyValues $data = null;

    /**
     * Returns the plugin data.
     *
     * @return NULL|PaymentSystemPropertyValues
     */
    public function getData(): ?PaymentSystemPropertyValues {
        return $this->data;
    }

    public function setData(array $data): void {
        $this->data = new PaymentSystemPropertyValues($data);
    }
}
