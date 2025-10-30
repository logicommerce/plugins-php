<?php

namespace Plugins\ComLogicommerceOct8ne\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::LICENSE 
 * @see PluginPropertiesPropertyNames::SERVER
 * @see PluginPropertiesPropertyNames::STATICURL
 * @see PluginPropertiesPropertyNames::POSITION
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOct8ne\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const USEREMAIL = 'userEmail';

    public const PASSWORD = 'password';

    public const LICENSE = 'license';

    public const SERVER = 'server';

    public const STATICURL = 'staticUrl';

    public const POSITION = 'position'; 

    public const REFERENCETYPE = 'referenceType';

}