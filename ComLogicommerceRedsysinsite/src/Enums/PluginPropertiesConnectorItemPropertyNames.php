<?php

namespace Plugins\ComLogicommerceRedsysinsite\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::MERCHANTCODE
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::MERCHANTNAME
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::MERCHANTPASSWORD
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::RESPONSEURL
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::TERMINAL
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::TOKENIZABLE
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLKO
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLOK
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceRedsysinsite\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const MERCHANTCODE = 'merchantCode';

    public const MERCHANTNAME = 'merchantName';

    public const MERCHANTPASSWORD = 'merchantPassword';

    public const RESPONSEURL = 'responseURL';

    public const TERMINAL = 'terminal';

    public const TOKENIZABLE = 'tokenizable';

    public const URLKO = 'urlKO';

    public const URLOK = 'urlOK';

}