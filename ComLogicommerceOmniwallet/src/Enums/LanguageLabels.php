<?php

namespace Plugins\ComLogicommerceOmniwallet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_OMNIWALLET_ACCOUNT_DATA
 * @see LanguageLabels::COM_LOGICOMMERCE_OMNIWALLET_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_OMNIWALLET_EMAIL
 * @see LanguageLabels::COM_LOGICOMMERCE_OMNIWALLET_CARD_NUMBER
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOmniwallet\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_OMNIWALLET_ACCOUNT_DATA = 'ComLogicommerceOmniwalletAccountData';

    public const COM_LOGICOMMERCE_OMNIWALLET_NAME = 'ComLogicommerceOmniwalletName';

    public const COM_LOGICOMMERCE_OMNIWALLET_EMAIL = 'ComLogicommerceOmniwalletEmail';

    public const COM_LOGICOMMERCE_OMNIWALLET_CARD_NUMBER = 'ComLogicommerceOmniwalletCardNumber';

}
