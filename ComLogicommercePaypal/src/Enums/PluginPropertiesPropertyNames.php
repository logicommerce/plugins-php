<?php

namespace Plugins\ComLogicommercePaypal\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::CRYPT_KEY
 * @see PluginPropertiesPropertyNames::ACCOUNT_ID
 * @see PluginPropertiesPropertyNames::MERCHANT_PASSWORD
 * @see PluginPropertiesPropertyNames::EXPRESS_CHECKOUT
 * @see PluginPropertiesPropertyNames::EXPRESS_CHECKOUT_CANCEL_URL
 * @see PluginPropertiesPropertyNames::EXPRESS_CHECKOUT_RETURN_URL
 * @see PluginPropertiesPropertyNames::ENVIRONMENT
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaypal\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const CRYPTKEY = 'cryptKey';

    public const ACCOUNTID = 'accountId';

    public const MERCHANTPASSWORD = 'merchantPassword';

    public const EXPRESSCHECKOUT = 'expressCheckout';

    public const EXPRESSCHECKOUTCANCELURL = 'expressCheckoutCancelUrl';

    public const EXPRESSCHECKOUTRETURNURL = 'expressCheckoutReturnUrl';

    public const ENVIRONMENT = 'environment';
}
