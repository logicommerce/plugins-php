<?php

namespace Plugins\ComLogicommerceMultisafepay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginDataActions::GET_API_TOKEN 
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMultisafepay\Enums;
 */
class PluginDataActions extends Enum {

    public const GET_API_TOKEN = 'getApiToken';

    public const GET_MERCHANT_APPLE_PAY = 'getMerchantApplePay';

}
