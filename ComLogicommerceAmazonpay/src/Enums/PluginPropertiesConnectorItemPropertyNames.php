<?php

namespace Plugins\ComLogicommerceAmazonpay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares connector property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 * @see PluginPropertiesConnectorItemPropertyNames::RETURNURL
 * @see PluginPropertiesConnectorItemPropertyNames::CANCELURL
 * @see PluginPropertiesConnectorItemPropertyNames::EXPRESSCHECKOUT
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAmazonpay\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const RETURNURL = 'returnUrl';

    public const CANCELURL = 'cancelUrl';

    public const EXPRESSCHECKOUT = 'expressCheckout';
}