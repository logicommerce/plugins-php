<?php

declare(strict_types=1);

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

    /**
     * Several widgets of one page in ONE request (`widgetIds=a,b,c`), same payload per widget as `getWidget`. The
     * editor uses it to repaint every widget that reads the product when the product simulator changes: one store
     * bootstrap instead of one per widget, and no reload of the canvas.
     */
    public const GET_WIDGETS = 'getWidgets';

    /** Request param of `getWidgets`: the comma-separated widget ids. */
    public const WIDGET_IDS_PARAM = 'widgetIds';

    public const CUSTOMIZE_CSS_JS = 'customizeCssJs';

    public const WIDGET_CONTENT = 'widgetContent';
}
