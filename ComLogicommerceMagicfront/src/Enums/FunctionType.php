<?php

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the FunctionType enumeration class.
 * This class declares FunctionType enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see Enum
 *
 * @package FWK\Enums
 */
abstract class FunctionType extends Enum {

    public const GET_WIDGET = 'getWidget';

    public const CUSTOMIZE_CSS = 'customizeCss';

    public const CUSTOMIZE_JS = 'customizeJs';
}
