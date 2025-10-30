<?php

namespace Plugins\ComLogicommercePaypal\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_BUY_FORM_SUBMIT
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_LOGIN
 * @see LanguageLabels::COM_LOGICOMMERCE_PAYPAL_TITLE
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaypal\Enums;
 */
class LanguageLabels extends Enum {
    public const COM_LOGICOMMERCE_PAYPAL_BUY_FORM_SUBMIT = 'ComLogicommercePaypalBuyFormSubmit';
    public const COM_LOGICOMMERCE_PAYPAL_LOGIN = 'ComLogicommercePaypalLogin';
    public const COM_LOGICOMMERCE_PAYPAL_TITLE = 'ComLogicommercePaypalTitle';
}
