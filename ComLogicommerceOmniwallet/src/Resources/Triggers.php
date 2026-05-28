<?php declare(strict_types=1);

namespace Plugins\ComLogicommerceOmniwallet\Resources;

use Plugins\ComLogicommerceOmniwallet\Enums\PluginDataActions;
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
* @package Plugins\ComLogicommerceOmniwallet\Resources
*/

class Triggers implements PluginTriggers {

    /**
    * Returns the triggers with the actions.
    *
    * @return array
    */
    public static function getTriggers(): array {
        $triggers[PluginEvents::SETTINGS] = [PluginDataActions::GET_SETTINGS];
        $triggers[PluginEvents::CUSTOMER] = [PluginDataActions::GET_CUSTOMER];
        return $triggers;
    }
}
