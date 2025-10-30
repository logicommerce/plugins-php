<?php

namespace Plugins\ComLogicommerceAmazonpay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_AMAZONPAY_BUY_FORM_SUBMIT
 * @see LanguageLabels::COM_LOGICOMMERCE_AMAZONPAY_LOGIN
 * @see LanguageLabels::COM_LOGICOMMERCE_AMAZONPAY_TITLE
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAmazonpay\Enums;
 */
class LanguageLabels extends Enum {
    public const COM_LOGICOMMERCE_AMAZONPAY_BUY_FORM_SUBMIT = 'ComLogicommerceAmazonpayBuyFormSubmit';
    public const COM_LOGICOMMERCE_AMAZONPAY_LOGIN = 'ComLogicommerceAmazonpayLogin';
    public const COM_LOGICOMMERCE_AMAZONPAY_TITLE = 'ComLogicommerceAmazonpayTitle';
}
