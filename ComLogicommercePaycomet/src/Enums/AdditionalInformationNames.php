<?php

namespace Plugins\ComLogicommercePaycomet\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the AdditionalInformationNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_ENTITY 
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_EXPIRY_DATE
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_REFERENCE
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER
 * @see AdditionalInformationNames::PAYCOMET_ACTION
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommercePaycomet\Enums;
 */

class AdditionalInformationNames extends Enum {

    public const PRESENT_TO_SHOPPER_ENTITY = 'entityNumber';

    public const PRESENT_TO_SHOPPER_EXPIRY_DATE = 'PresentToShopperExpireDate';

    public const PRESENT_TO_SHOPPER_REFERENCE = 'referenceNumber';

    public const PRESENT_TO_SHOPPER = "presenttoshopper";

    public const PAYCOMET_ACTION = 'paycometAction';
}