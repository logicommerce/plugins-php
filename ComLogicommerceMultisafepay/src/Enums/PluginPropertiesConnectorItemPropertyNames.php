<?php

namespace Plugins\ComLogicommerceMultisafepay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares connector property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract 
 * @see PluginPropertiesConnectorItemPropertyNames::ENVIRONMENT
 * @see PluginPropertiesConnectorItemPropertyNames::SUBPAYMENTSYSTEMID
 * @see PluginPropertiesConnectorItemPropertyNames::APYKEYTEST
 * @see PluginPropertiesConnectorItemPropertyNames::APIKEY
 * @see PluginPropertiesConnectorItemPropertyNames::URLSUCCESS
 * @see PluginPropertiesConnectorItemPropertyNames::URLNOTIFY
 * @see PluginPropertiesConnectorItemPropertyNames::URLFAILED
 * @see PluginPropertiesConnectorItemPropertyNames::TOKENIZABLE
 * @see PluginPropertiesConnectorItemPropertyNames::REDIRECTGATEWAY
 * @see PluginPropertiesConnectorItemPropertyNames::SECONDCHANCEEMAIL
 * @see PluginPropertiesConnectorItemPropertyNames::GAACCOUNTID
 * @see PluginPropertiesConnectorItemPropertyNames::SITEID
 * @see PluginPropertiesConnectorItemPropertyNames::SENDUTMNOOVERRIDE1
 * @see PluginPropertiesConnectorItemPropertyNames::IDEALISSUERID
 * @see PluginPropertiesConnectorItemPropertyNames::DISABLECONFIRMEMAIL
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMultisafepay\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const ENVIRONMENT = 'environment';
 
    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const APIKEYTEST = "apiKeyTest";

    public const APIKEY = "apiKey";

    public const URLSUCCESS = "urlSuccess";

    public const URLNOTIFY = 'urlNotify';

    public const URLFAILED = "urlFailed";

    public const TOKENIZABLE = 'tokenizable';

    public const REDIRECTGATEWAY = 'redirectGateway';

    public const SECONDCHANCEEMAIL = 'secondChanceEmail';

    public const GAACCOUNTID = 'gaAccountId';

    public const GAACCOUNTNAME = 'gaAccountName';

    public const SITEID = 'siteId';

    public const SENDUTMNOOVERRIDE1 = "sendUtmNoOverride1";

    public const IDEALISSUERID = "idealIssuerId";

    public const DISABLECONFIRMEMAIL = "disableConfirmEmail";
}