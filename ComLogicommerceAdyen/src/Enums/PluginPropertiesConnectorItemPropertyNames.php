<?php

namespace Plugins\ComLogicommerceAdyen\Enums;

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
 * @package Plugins\ComLogicommerceAdyen\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const CLIENTKEY = 'clientKey';

    public const MERCHANTACCOUNT = 'merchantAccount';

    public const FRONTURL = 'frontURL';

    public const DENIEDURL = 'deniedURL';

    public const BACKURL = 'backURL';

    public const URLOK = 'urlOK';

    public const ENVIRONMENT = 'environment';
 
    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const CONFIGSCRIPT = "configScript";

    public const ACTIVECONFIGSCRIPT = "activeConfigScript";
}