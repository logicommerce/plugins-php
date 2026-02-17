<?php

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the API available resources.
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class Resource extends Enum {

    public const GET_PAGE_WIDGETS = "/pages/{pageId}/widgets";

    public const GET_PAGE_WIDGET_BY_ID = "/pages/widgets/{widgetId}";

    public const GET_WIDGET_TEMPLATES = "/widgetTemplates";

    public const GET_WIDGET_TEMPLATE_BY_TYPE = "/widgetTemplates/{type}";

    public const GET_PAGES = "/pages";

    public const AUTH = "/auth";
}
