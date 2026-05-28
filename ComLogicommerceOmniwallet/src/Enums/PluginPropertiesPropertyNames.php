<?php

namespace Plugins\ComLogicommerceOmniwallet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesPropertyNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::APITOKEN 
 * @see PluginPropertiesPropertyNames::ACCOUNTNAME
 * @see PluginPropertiesPropertyNames::MAXREDEEM
 * @see PluginPropertiesPropertyNames::CONVERSIONRULE
 * @see PluginPropertiesPropertyNames::USERREGISTRATIONPOINTS
 * @see PluginPropertiesPropertyNames::NEWSLETTERPOINTS
 * @see PluginPropertiesPropertyNames::MINAMOUNTPURCHASEPOINTS
 * @see PluginPropertiesPropertyNames::APIURL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOmniwallet\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const APITOKEN = 'apiToken';

    public const ACCOUNTNAME = 'accountName';

    public const MAXREDEEM = 'maxRedeem';

    public const CONVERSIONRULE = 'conversionRule';

    public const USERREGISTRATIONPOINTS = 'userRegistrationPoints';

    public const NEWSLETTERPOINTS = 'newsletterPoints'; 

    public const MINAMOUNTPURCHASEPOINTS = 'minAmountPurchasePoints';

}