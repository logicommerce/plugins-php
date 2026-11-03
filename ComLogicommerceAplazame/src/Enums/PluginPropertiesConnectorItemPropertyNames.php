<?php

namespace Plugins\ComLogicommerceAplazame\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginPropertiesConnectorItemPropertyNames enumeration class.
 * This class declares the payment system connector property names.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesConnectorItemPropertyNames::SUBPAYMENTSYSTEMID
 * @see PluginPropertiesConnectorItemPropertyNames::NOTIFICATIONURL
 * @see PluginPropertiesConnectorItemPropertyNames::SUCCESSURL
 * @see PluginPropertiesConnectorItemPropertyNames::KOURL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAplazame\Enums;
 */
class PluginPropertiesConnectorItemPropertyNames extends Enum {

    public const SUBPAYMENTSYSTEMID = 'subPaymentSystemId';

    public const NOTIFICATIONURL = 'notificationUrl';

    public const SUCCESSURL = 'successUrl';

    public const KOURL = 'koUrl';

}
