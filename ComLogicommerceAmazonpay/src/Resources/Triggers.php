<?php declare(strict_types=1);

namespace Plugins\ComLogicommerceAmazonpay\Resources;

use Plugins\ComLogicommerceAmazonpay\Enums\PluginDataActions;
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
* @package Plugins\ComLogicommerceAmazonpay\Resources
*/

class Triggers implements PluginTriggers {

    /**
    * Returns the triggers with the actions.
    *
    * @return array
    */
    public static function getTriggers(): array {
        $triggers[PluginEvents::SELECT_PAYMENT_SYSTEM] = [PluginDataActions::GET_AMZ_DATA, PluginDataActions::GET_AMZ_SESSION];
        $triggers[PluginEvents::LOGIN_EVENT] = [PluginDataActions::LOGIN_AMZ];
        return $triggers;
    }
}
