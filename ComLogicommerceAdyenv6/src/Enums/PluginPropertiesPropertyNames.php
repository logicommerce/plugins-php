<?php

namespace Plugins\ComLogicommerceAdyenv6\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::PAYMENTAPIURL 
 * @see PluginPropertiesPropertyNames::APIRECURRINGVERSION
 * @see PluginPropertiesPropertyNames::SANDBOXURL
 * @see PluginPropertiesPropertyNames::SANDBOXPAYMENTAPIURL
 * @see PluginPropertiesPropertyNames::APIURL
 * @see PluginPropertiesPropertyNames::APIKEY
 * @see PluginPropertiesPropertyNames::APIVERSION
 * @see PluginPropertiesPropertyNames::CANCELLED
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAdyenv6\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const PAYMENTAPIURL = 'paymentApiURL';

    public const APIRECURRINGVERSION = 'apiRecurringVersion';

    public const SANDBOXURL = 'sandboxURL';

    public const SANDBOXPAYMENTAPIURL = 'sandboxPaymentApiURL';

    public const APIURL = 'apiURL';

    public const APIKEY = 'apiKey';

    public const APIVERSION = 'apiVersion';
    
    public const CANCELLED = 'cancelled';

    public const CLIENTKEY = 'clientKey';

    public const MERCHANTACCOUNT = 'merchantAccount';
    
    public const ENVIRONMENT = 'environment';

}