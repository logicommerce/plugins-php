<?php

declare(strict_types=1);

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

    public const WIDGET_TEMPLATES_BASE = "/widgetTemplates/";

    public const GET_WIDGET_TEMPLATE_BY_ID = self::WIDGET_TEMPLATES_BASE . "{id}";

    //public const GET_PAGES = "/pages"; 

    /** Single page record by id; its `chrome` field carries the {header,footer} chrome doc ids. */
    public const GET_PAGE_BY_ID = "/pages/{pageId}";  

    //public const AUTH = "/auth";

    /**
     * Site chrome doc addressed by its own id (= root widget id). Returns the chrome
     * widget tree (header or footer) for that doc. `{id}` is the chrome doc id the page
     * points at via `page.chrome.{header|footer}`; query `language`. The per-commerce
     * DEFAULT of a kind is read through this same endpoint with `{id}` = the kind token
     * ("header"/"footer") and query `default=true` (the backend lazy-seeds it) — there is
     * no dedicated defaults endpoint.
     */
    public const GET_CHROME_DOC = "/chrome/{id}";
}
