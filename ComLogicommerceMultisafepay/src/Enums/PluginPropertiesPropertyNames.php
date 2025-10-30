<?php

namespace Plugins\ComLogicommerceMultisafepay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::ACCOUNTID
 * @see PluginPropertiesPropertyNames::APIURLTEST
 * @see PluginPropertiesPropertyNames::APIURLLIVE
 * @see PluginPropertiesPropertyNames::CANCELLED
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMultisafepay\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const ACCOUNTID = 'accountId';

    public const APIURLTEST = 'apiUrlTest';

    public const APIURLLIVE = 'apiUrlLive';

    public const CANCELLED = 'cancelled';
 
}