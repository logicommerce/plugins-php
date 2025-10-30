<?php

namespace Plugins\ComLogicommerceAdyen\Enums;

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
 * @see AdditionalInformationNames::ADYEN_ACTION
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceAdyen\Enums;
 */

class AdditionalInformationNames extends Enum {

    public const PRESENT_TO_SHOPPER_ENTITY = 'PresentToShopperEntity';

    public const PRESENT_TO_SHOPPER_EXPIRY_DATE = 'PresentToShopperExpireDate';

    public const PRESENT_TO_SHOPPER_REFERENCE = 'PresentToShopperReference';

    public const PRESENT_TO_SHOPPER_QR_CODEDATA = 'PresentToShopperQrCodeData';

    public const PRESENT_TO_SHOPPER = "presenttoshopper";

    public const ADYEN_ACTION = 'adyenAction';
}