<?php

namespace Plugins\ComLogicommerceSequra\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_SEQURA_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_SEQURA_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_SEQURA_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_SEQURA_SAVE_TOKEN
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceSequra\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_SEQURA_IDENTIFIED = 'ComLogicommerceSequraIdentifier';

    public const COM_LOGICOMMERCE_SEQURA_CARD_NUMBER = 'ComLogicommerceSequraCardNumber';

    public const COM_LOGICOMMERCE_SEQURA_EXPIRY_DATE = 'ComLogicommerceSequraExpiryDate';

    public const COM_LOGICOMMERCE_SEQURA_SAVE_TOKEN = 'ComLogicommerceSequraSaveToken';

}
