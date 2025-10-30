<?php

namespace Plugins\ComLogicommerceAdyen\Enums;

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
 * @package Plugins\ComLogicommerceAdyen\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_ADYEN_IDENTIFIED = 'ComLogicommerceAdyenIdentifier';

    public const COM_LOGICOMMERCE_ADYEN_REFERENCE = 'ComLogicommerceAdyenReference';

    public const COM_LOGICOMMERCE_ADYEN_CARD_NUMBER = 'ComLogicommerceAdyenCardNumber';

    public const COM_LOGICOMMERCE_ADYEN_EXPIRY_DATE = 'ComLogicommerceAdyenExpiryDate';

    public const COM_LOGICOMMERCE_ADYEN_SAVE_TOKEN = 'ComLogicommerceAdyenSaveToken';

    public const COM_LOGICOMMERCE_ADYEN_ENTITY = 'ComLogicommerceAdyenEntity';

    public const COM_LOGICOMMERCE_ADYEN_TOTAL = 'ComLogicommerceAdyenTotal';

}
