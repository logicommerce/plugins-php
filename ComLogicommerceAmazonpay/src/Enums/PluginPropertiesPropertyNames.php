<?php

namespace Plugins\ComLogicommerceAmazonpay\Enums;

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
 * @package Plugins\ComLogicommerceAmazonpay\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const STOREID = 'storeId';

    public const SUPPLIERID = 'supplierId';

    public const APIVERSION = 'apiVersion';

    public const ACCOUNTORIGIN = 'accountOrigin';

    public const SANDBOXAPIURL = 'sandboxApiUrl';

    public const APIURL = 'apiUrl';

    public const PRIVATEKEY = 'privateKey';

    public const PUBLICKEY = 'publicKey';

    public const MERCHANTID = 'merchantId';

    public const ENVIRONMENT = 'environment';

    public const EXPRESSCHECKOUT = 'expressCheckout';

    public const AMZLOGIN = 'amzLogin';
}
