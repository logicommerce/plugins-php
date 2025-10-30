<?php

namespace Plugins\ComLogicommerceAmazonpay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginDataActions::GET_AMZ_DATA 
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAmazonpay\Enums;
 */
class PluginDataActions extends Enum {

    public const GET_AMZ_DATA = 'getAmzData';

    public const LOGIN_AMZ = 'loginAmz';

    public const GET_AMZ_SESSION = 'getAmzSession';

}
