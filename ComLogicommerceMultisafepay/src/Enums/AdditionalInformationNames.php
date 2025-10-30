<?php

namespace Plugins\ComLogicommerceMultisafepay\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the AdditionalInformationNames enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_REFERENCE
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_ENTITY
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_IBAN
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_NAME
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_BIC
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER_QR_CODE_DATA
 * @see AdditionalInformationNames::PRESENT_TO_SHOPPER
 * @see AdditionalInformationNames::MSP_ACTION
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMultisafepay\Enums;
 */

class AdditionalInformationNames extends Enum {

    public const PRESENT_TO_SHOPPER_ENTITY = 'PresentToShopperEntity';

    public const PRESENT_TO_SHOPPER_REFERENCE = 'PresentToShopperReference';

    public const PRESENT_TO_SHOPPER_IBAN = 'PresentToShopperIban';

    public const PRESENT_TO_SHOPPER_BIC = 'PresentToShopperBic';

    public const PRESENT_TO_SHOPPER_NAME = 'PresentToShopperName';

    public const PRESENT_TO_SHOPPER = "presenttoshopper";

    public const PRESENT_TO_SHOPPER_QR_CODE_DATA = "PresentToShopperQrCodeData";

    public const MSP_ACTION = 'mspAction';
}