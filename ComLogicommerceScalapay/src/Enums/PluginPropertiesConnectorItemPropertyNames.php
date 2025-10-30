<?php

namespace Plugins\ComLogicommerceScalapay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceScalapay\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const RESPONSEURL = 'responseURL';

    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const ENVIRONMENT = 'environment';

    public const URLKO = 'urlKO';

    public const URLOK = 'urlOK';

    public const SHOWWIDGET = 'showWidget';

}