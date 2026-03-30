<?php

namespace Plugins\ComLogicommercePaypal\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares connector property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract 
 * @see PluginPropertiesConnectorItemPropertyNames::PAYMENTSYSTEMRETURNURL 
 * @see PluginPropertiesConnectorItemPropertyNames::PAYMENTSYSTEMCANCELURL
 * @see PluginPropertiesConnectorItemPropertyNames::EXPRESSCHECKOUT
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaypal\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const PAYMENTSYSTEMRETURNURL = 'paymentSystemReturnUrl';

    public const PAYMENTSYSTEMCANCELURL = 'paymentSystemCancelUrl';

    public const EXPRESSCHECKOUT = 'expressCheckout';

}