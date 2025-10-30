<?php

namespace Plugins\ComLogicommercePaypalcommerceplatform\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_CCV
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_CARD
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_PAY_CARD
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_PLATFORM_SAVE_TOKEN
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaypalcommerceplatform\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_CCV = 'ComLogicommercePaypalPlatformCcv';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_CARD = 'ComLogicommercePaypalPlatformCard';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_EXPIRY_DATE = 'ComLogicommercePaypalPlatformExpiryDate';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_PAY_CARD = 'ComLogicommercePaypalPlatformPayCard';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_IDENTIFIED = 'ComLogicommercePaypalPlatformIdentifier';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_CARD_NUMBER = 'ComLogicommercePaypalPlatformCardNumber';

    public const COM_LOGICOMMERCE_PAYPAL_PLATFORM_SAVE_TOKEN = 'ComLogicommercePaypalPlatformSaveToken';

}