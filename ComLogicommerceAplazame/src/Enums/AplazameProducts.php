<?php

namespace Plugins\ComLogicommerceAplazame\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the AplazameProducts enumeration class.
 * This class declares the Aplazame products that can be configured in the payment system.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see AplazameProducts::INSTALMENTS
 * @see AplazameProducts::PAY_LATER
 * @see AplazameProducts::PAY_IN_4
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAplazame\Enums;
 */
class AplazameProducts extends Enum {

    public const INSTALMENTS = 'instalments';

    public const PAY_LATER = 'pay_later';

    public const PAY_IN_4 = 'pay_in_4';

}
