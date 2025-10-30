<?php

namespace Plugins\ComLogicommerceAdyen\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginDataActions::GET_PAYMENT_METHODS 
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAdyen\Enums;
 */
class PluginDataActions extends Enum {

    public const GET_PAYMENT_METHODS = 'getPaymentMethods';

}
