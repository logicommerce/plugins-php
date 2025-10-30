<?php

namespace Plugins\ComLogicommercePaypalcommerceplatform\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 *  * @see PluginPropertiesConnectorItemPropertyNames::SUBPAYMENTS 
 * 
 *  * @see PluginPropertiesConnectorItemPropertyNames::RETURNURL 
 * 
 *  * @see PluginPropertiesConnectorItemPropertyNames::URLKO 
 * 
 *  * @see PluginPropertiesConnectorItemPropertyNames::URLOK 
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaypalcommerceplatform\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const SUBPAYMENTS = 'subpayments';

    public const RETURNURL = 'returnUrl';

    public const URLKO = 'urlKO';

    public const URLOK = 'urlOK';

}