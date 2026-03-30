<?php declare(strict_types=1);

namespace Plugins\ComLogicommerceAdyenv6\Resources;

use Plugins\ComLogicommerceAdyenv6\Enums\PluginDataActions;
use SDK\Core\Interfaces\PluginTriggers;
use SDK\Enums\PluginEvents;

/**
* This is the plugin Trigger class in the LogiCommerce SDK package.
* For execute actions when triggers
*
* @see Triggers::getTriggers()
*
* @see PluginTriggers
*
* @package Plugins\ComLogicommerceAdyenv6\Resources
*/

class Triggers implements PluginTriggers {

    /**
    * Returns the triggers with the actions.
    *
    * @return array
    */
    public static function getTriggers(): array {
        $triggers[PluginEvents::SELECT_PAYMENT_SYSTEM] = [PluginDataActions::GET_PAYMENT_METHODS];
        return $triggers;
    }
}