<?php

namespace Plugins\ComLogicommerceBizum\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_BIZUM_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_BIZUM_CARD_NUMBER
 * @see LanguageLabels::COM_LOGICOMMERCE_BIZUM_EXPIRY_DATE
 * @see LanguageLabels::COM_LOGICOMMERCE_BIZUM_SAVE_TOKEN
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceBizum\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_BIZUM_IDENTIFIED = 'ComLogicommerceBizumIdentifier';

    public const COM_LOGICOMMERCE_BIZUM_CARD_NUMBER = 'ComLogicommerceBizumCardNumber';

    public const COM_LOGICOMMERCE_BIZUM_EXPIRY_DATE = 'ComLogicommerceBizumExpiryDate';

    public const COM_LOGICOMMERCE_BIZUM_SAVE_TOKEN = 'ComLogicommerceBizumSaveToken';

}
