<?php

namespace Plugins\ComLogicommerceMultisafepay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_SAVE_TOKEN
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_ENTITY
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_TOTAL
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_SHOPPER_REFERENCE
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_IBAN
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_BIC
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_LINK
 * @see LanguageLabels::COM_LOGICOMMERCE_MULTISAFEPAY_ENTITY
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMultisafepay\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_MULTISAFEPAY_IDENTIFIED = 'ComLogicommerceMultisafepayIdentifier';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_REFERENCE = 'ComLogicommerceMultisafepayReference';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_CARD_NUMBER = 'ComLogicommerceMultisafepayCardNumber';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_EXPIRY_DATE = 'ComLogicommerceMultisafepayExpiryDate';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_SAVE_TOKEN = 'ComLogicommerceMultisafepaySaveToken';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_ENTITY = 'ComLogicommerceMultisafepayEntity';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_TOTAL = 'ComLogicommerceMultisafepayTotal';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_SHOPPER_REFERENCE = 'ComLogicommerceMultisafepayShopperReference';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_SHOPPER_ENTITY = 'ComLogicommerceMultisafepayShopperEntity';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_IBAN = 'ComLogicommerceMultisafepayIban';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_BIC = 'ComLogicommerceMultisafepayBic';

    public const COM_LOGICOMMERCE_MULTISAFEPAY_NAME = 'ComLogicommerceMultisafepayName';
    
    public const COM_LOGICOMMERCE_MULTISAFEPAY_LINK = 'ComLogicommerceMultisafepayLink';

}
