<?php

namespace Plugins\ComLogicommerceRedsys\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_REDSYS_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_REDSYS_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_REDSYS_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_REDSYS_SAVE_TOKEN
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceRedsys\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_REDSYS_IDENTIFIED = 'ComLogicommerceRedsysIdentifier';

    public const COM_LOGICOMMERCE_REDSYS_CARD_NUMBER = 'ComLogicommerceRedsysCardNumber';

    public const COM_LOGICOMMERCE_REDSYS_EXPIRY_DATE = 'ComLogicommerceRedsysExpiryDate';

    public const COM_LOGICOMMERCE_REDSYS_SAVE_TOKEN = 'ComLogicommerceRedsysSaveToken';

}
