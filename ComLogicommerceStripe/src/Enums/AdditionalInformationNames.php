<?php

namespace Plugins\ComLogicommerceStripe\Enums;

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
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_QR_CODEDATA
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER
 * @see AdditionalInformationNames::STRIPE_ACTION
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceStripe\Enums;
 */

class AdditionalInformationNames extends Enum {

    public const PRESENT_TO_SHOPPER_ENTITY = 'PresentToShopperEntity';

    public const PRESENT_TO_SHOPPER_EXPIRY_DATE = 'PresentToShopperExpireDate';

    public const PRESENT_TO_SHOPPER_REFERENCE = 'PresentToShopperReference';

    public const PRESENT_TO_SHOPPER_QR_CODEDATA = 'PresentToShopperQrCodeData';

    public const PRESENT_TO_SHOPPER_INSTRUCTIONS = 'PresentToShopperInstructions';

    public const PRESENT_TO_SHOPPER = "PresenttoShopper";

    public const STRIPE_ACTION = 'stripeAction';
}