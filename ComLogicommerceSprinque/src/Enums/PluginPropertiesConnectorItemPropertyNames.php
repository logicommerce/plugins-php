<?php

namespace Plugins\ComLogicommerceSprinque\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesConnectorItemPropertyNames::CUSTOMIZABLE
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::DIRECTPAYMENT
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
 * @see PluginPropertiesConnectorItemPropertyNames::URL
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLKO
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLOK
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceSprinque\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const ENVIRONMENT = 'environment';

    public const APIURL = 'apiUrl';

    public const APIURLTEST = 'apiUrlTest';

    public const APIKEY = 'apiKey';

    public const APIKEYTEST = 'apiKeyTest';

    public const VERSION = 'version';

    public const PAYMENTTERMS = 'paymentTerms';

}