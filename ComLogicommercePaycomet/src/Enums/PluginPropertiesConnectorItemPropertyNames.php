<?php

namespace Plugins\ComLogicommercePaycomet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesConnectorItemPropertyNames::ACCOUNTNAME
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::PASSWORD
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::TERMINAL
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::TOKENIZABLE
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLKO
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::URLOK
 * 
 * @see PluginPropertiesConnectorItemPropertyNames::SUBPAYMENT
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaycomet\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const ACCOUNTCODE = 'accountCode';

    public const PASSWORD = 'password';

    public const TERMINAL = 'terminal';

    public const TOKENIZATION = 'tokenization';

    public const URLKO = 'urlKo';

    public const URLOK = 'urlOk';

    public const SUBPAYMENT = 'subpayment';

}