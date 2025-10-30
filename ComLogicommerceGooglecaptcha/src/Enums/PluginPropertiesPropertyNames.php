<?php

namespace Plugins\ComLogicommerceGooglecaptcha\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the property definition enum for google maps.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::ERRORONCACHEABLEZEROTTL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceGooglecaptcha\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const SITEKEY = 'siteKey';

    public const APIKEY = 'apiKey';

    public const BOTSCORE = 'botScore';
    
}