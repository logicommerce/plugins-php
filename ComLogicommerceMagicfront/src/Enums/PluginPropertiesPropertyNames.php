<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares plugin property name enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @see PluginPropertiesPropertyNames::ENVIRONMENT
 * @see PluginPropertiesPropertyNames::AVAILABLEPAGES
 * @see PluginPropertiesPropertyNames::APIURL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class PluginPropertiesPropertyNames extends Enum {

    public const ENVIRONMENT = 'environment';

    public const AVAILABLEPAGES = 'availablepages';

    public const APIURL = 'apiUrl';
}
