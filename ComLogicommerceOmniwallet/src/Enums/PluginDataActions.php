<?php

namespace Plugins\ComLogicommerceOmniwallet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginDataActions::GET_SETTINGS
 * @see PluginDataActions::GET_CUSTOMER
 * @see PluginDataActions::GET_TOKEN
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOmniwallet\Enums;
 */
class PluginDataActions extends Enum {

    public const GET_SETTINGS = 'getSettings';

    public const GET_CUSTOMER = 'getCustomer';
    
    public const GET_TOKEN = 'getToken';

}
