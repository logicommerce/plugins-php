<?php

namespace Plugins\ComLogicommerceAplazame\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares the plugin property names that can be used in the front.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::PUBLICKEY
 * @see PluginPropertiesPropertyNames::SANDBOX
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAplazame\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const PUBLICKEY = 'publicKey';

    public const SANDBOX = 'sandbox';

}
