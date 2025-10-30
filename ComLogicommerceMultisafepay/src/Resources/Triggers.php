<?php declare(strict_types=1);

namespace Plugins\ComLogicommerceMultisafepay\Resources;

use Plugins\ComLogicommerceMultisafepay\Enums\PluginDataActions;
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
* @package Plugins\ComLogicommerceMultisafepay\Resources
*/

class Triggers implements PluginTriggers {

    /**
    * Returns the triggers with the actions.
    *
    * @return array
    */
    public static function getTriggers(): array {
        $triggers[PluginEvents::SELECT_PAYMENT_SYSTEM] = [PluginDataActions::GET_API_TOKEN];
        $triggers[PluginEvents::LOGIN_EVENT] = [PluginDataActions::GET_MERCHANT_APPLE_PAY];
        return $triggers;
    }
}