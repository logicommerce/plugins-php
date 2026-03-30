<?php

namespace Plugins\ComLogicommerceAdyenv6\Enums;

use COM;
use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_SAVE_TOKEN
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_ENTITY
 * @see LanguageLabels::COM_LOGICOMMERCE_ADYEN_TOTAL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAdyenv6\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_ADYENV6_IDENTIFIED = 'ComLogicommerceAdyenV6Identifier';

    public const COM_LOGICOMMERCE_ADYENV6_REFERENCE = 'ComLogicommerceAdyenV6Reference';

    public const COM_LOGICOMMERCE_ADYENV6_CARD_NUMBER = 'ComLogicommerceAdyenV6CardNumber';

    public const COM_LOGICOMMERCE_ADYENV6_EXPIRY_DATE = 'ComLogicommerceAdyenV6ExpiryDate';

    public const COM_LOGICOMMERCE_ADYENV6_SAVE_TOKEN = 'ComLogicommerceAdyenV6SaveToken';

    public const COM_LOGICOMMERCE_ADYENV6_ENTITY = 'ComLogicommerceAdyenV6Entity';

    public const COM_LOGICOMMERCE_ADYENV6_TOTAL = 'ComLogicommerceAdyenV6Total';

    public const COM_LOGICOMMERCE_ADYENV6_SET_PAYMENT_METHOD = 'ComLogicommerceAdyenV6SetPaymentMethod';

    public const COM_LOGICOMMERCE_ADYENV6_PROCESSING_ORDER = 'ComLogicommerceAdyenV6ProcessingOrder';

    public const COM_LOGICOMMERCE_ADYENV6_CREATING_ORDER = 'ComLogicommerceAdyenV6CreatingOrder';

    public const COM_LOGICOMMERCE_ADYENV6_EX_TOTAL = 'ComLogicommerceAdyenV6ExTotal';

    public const COM_LOGICOMMERCE_ADYENV6_SUBTOTAL = 'ComLogicommerceAdyenV6Subtotal';

    public const COM_LOGICOMMERCE_ADYENV6_EX_SHIPPING = 'ComLogicommerceAdyenV6ExShipping';

    public const COM_LOGICOMMERCE_ADYENV6_EX_DISCOUNTS = 'ComLogicommerceAdyenV6ExDiscounts';

    public const COM_LOGICOMMERCE_ADYENV6_EX_TAX = 'ComLogicommerceAdyenV6ExTax';

    public const COM_LOGICOMMERCE_ADYENV6_EX_TAX_BASE = 'ComLogicommerceAdyenV6ExTaxBase';

    public const COM_LOGICOMMERCE_ADYENV6_PROCESSING_PAYMENT = 'ComLogicommerceAdyenV6ProcessingPayment';

    public const COM_LOGICOMMERCE_ADYENV6_ERROR_APPLE_PAY = 'ComLogicommerceAdyenV6ErrorApplePay';

}
