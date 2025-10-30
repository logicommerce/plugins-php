<?php

namespace Plugins\ComLogicommerceStripe\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_SAVE_TOKEN
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_ENTITY
 * @see LanguageLabels::COM_LOGICOMMERCE_STRIPE_TOTAL
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceStripe\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_STRIPE_IDENTIFIED = 'ComLogicommerceStripeIdentifier';

    public const COM_LOGICOMMERCE_STRIPE_REFERENCE = 'ComLogicommerceStripeReference';

    public const COM_LOGICOMMERCE_STRIPE_CARD_NUMBER = 'ComLogicommerceStripeCardNumber';

    public const COM_LOGICOMMERCE_STRIPE_EXPIRY_DATE = 'ComLogicommerceStripeExpiryDate';

    public const COM_LOGICOMMERCE_STRIPE_SAVE_TOKEN = 'ComLogicommerceStripeSaveToken';

    public const COM_LOGICOMMERCE_STRIPE_ENTITY = 'ComLogicommerceStripeEntity';

    public const COM_LOGICOMMERCE_STRIPE_TOTAL = 'ComLogicommerceStripeTotal';

    public const COM_LOGICOMMERCE_STRIPE_INSTRUCTIONS = 'ComLogicommerceStripeInstructions';

}
