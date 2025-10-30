<?php

namespace Plugins\ComLogicommerceSequra\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesConnectorItemPropertyNames::RESPONSEURL
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::SUBPAYMENTSYSTEMID
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::ADQUIRERBIN
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLKO
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLOK
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceSequra\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const RESPONSEURL = 'responseURL';

    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const ADQUIRERBIN = 'adquirerBin';

    //public const URL = 'url';

    public const URLKO = 'urlKO';

    public const URLOK = 'urlOK';

}