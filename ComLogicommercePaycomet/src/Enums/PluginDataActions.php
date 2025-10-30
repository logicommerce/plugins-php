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
 * @see PluginDataActions::GET_LIST_RECURRING_DETAILS
 * 
 * @see PluginDataActions::DISABLE_CARDS
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaycomet\Enums;
 */
class PluginDataActions extends Enum {

    public const GET_LIST_RECURRING_DETAILS = 'getListRecurringDetails';

    public const DISABLE_CARDS = 'disableCards';

}
