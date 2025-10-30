<?php

namespace Plugins\ComLogicommerceStripe\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::APIURL 
 * @see PluginPropertiesPropertyNames::APIVERSION
 * @see PluginPropertiesPropertyNames::PUBLICKEY
 * @see PluginPropertiesPropertyNames::SECRETKEY
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceStripe\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const APIURL = 'apiUrl';

    public const APIVERSION = 'apiVersion';

    public const PUBLICKEY = 'publicKey';

    public const SECRETKEY = 'secretKey';

    public const WALLETS = 'wallets';
 
}