<?php

namespace Plugins\ComLogicommerceStripe\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares connector property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract 
 * @see PluginPropertiesConnectorItemPropertyNames::RETURNURL 
 * @see PluginPropertiesConnectorItemPropertyNames::ASYNCURL
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceStripe\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const RETURNURL = 'returnUrl';

    public const ASYNCURL = 'asyncUrl';

    public const PAYMENTMETHOD = 'paymentMethod';
}