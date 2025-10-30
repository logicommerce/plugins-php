<?php

namespace Plugins\ComLogicommercePaycomet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_SAVE_TOKEN
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_ENTITY
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYCOMET_TOTAL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaycomet\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_PAYCOMET_IDENTIFIED = 'ComLogicommercePaycometIdentifier';

    public const COM_LOGICOMMERCE_PAYCOMET_REFERENCE = 'ComLogicommercePaycometReference';

    public const COM_LOGICOMMERCE_PAYCOMET_CARD_NUMBER = 'ComLogicommercePaycometCardNumber';

    public const COM_LOGICOMMERCE_PAYCOMET_EXPIRY_DATE = 'ComLogicommercePaycometExpiryDate';

    public const COM_LOGICOMMERCE_PAYCOMET_SAVE_TOKEN = 'ComLogicommercePaycometSaveToken';

    public const COM_LOGICOMMERCE_PAYCOMET_ENTITY = 'ComLogicommercePaycometEntity';

    public const COM_LOGICOMMERCE_PAYCOMET_TOTAL = 'ComLogicommercePaycometTotal';

}
