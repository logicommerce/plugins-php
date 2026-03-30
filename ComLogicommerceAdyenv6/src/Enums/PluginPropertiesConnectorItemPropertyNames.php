<?php

namespace Plugins\ComLogicommerceAdyenv6\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares connector property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract 
 * @see PluginPropertiesConnectorItemPropertyNames::CLIENTKEY 
 * @see PluginPropertiesConnectorItemPropertyNames::MERCHANTACCOUNT
 * @see PluginPropertiesConnectorItemPropertyNames::FRONTURL
 * @see PluginPropertiesConnectorItemPropertyNames::DENIEDURL
 * @see PluginPropertiesConnectorItemPropertyNames::BACKURL
 * @see PluginPropertiesConnectorItemPropertyNames::URLOK
 * @see PluginPropertiesConnectorItemPropertyNames::ENVIRONMENT
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAdyenv6\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {


    public const FRONTURL = 'frontURL';

    public const DENIEDURL = 'deniedURL';

    public const BACKURL = 'backURL';
 
    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const EXPRESSCHECKOUT = 'expressCheckout';
}