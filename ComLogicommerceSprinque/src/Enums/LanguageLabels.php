<?php

namespace Plugins\ComLogicommerceSprinque\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the LanguageLabels enumeration class.
 * This class declares language labels enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see LanguageLabels::COM_LOGICOMMERCE_SPRINQUE_NAME
 * @see LanguageLabels::COM_LOGICOMMERCE_SPRINQUE_IDENTIFIED
 * @see LanguageLabels::COM_LOGICOMMERCE_SPRINQUE_CREDIT_STAUTS
 * @see LanguageLabels::COM_LOGICOMMERCE_SPRINQUE_CREDIT_LIMIT
 * @see LanguageLabels::COM_LOGICOMMERCE_SPRINQUE_AVAILABLE_CREDIT_LIMIT
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceSprinque\Enums;
 */
class LanguageLabels extends Enum {

    public const COM_LOGICOMMERCE_SPRINQUE_NAME = 'ComLogicommerceSprinqueName';

    public const COM_LOGICOMMERCE_SPRINQUE_IDENTIFIED = 'ComLogicommerceSprinqueIdentifier';

    public const COM_LOGICOMMERCE_SPRINQUE_CREDIT_STAUTS = 'ComLogicommerceSprinqueCreditStatus';

    public const COM_LOGICOMMERCE_SPRINQUE_CREDIT_LIMIT = 'ComLogicommerceSprinqueCreditLimit';

    public const COM_LOGICOMMERCE_SPRINQUE_AVAILABLE_CREDIT_LIMIT = 'ComLogicommerceSprinqueAvailableCreditLimit';

}
