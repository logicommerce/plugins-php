<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;

/**
 * Contract for a per-account-widget data fetcher. Registered in {@see DataWidgetRegistry::MAP} and
 * invoked by both the full-page render ({@see SharedDataProvider}) and the widgetContent AJAX endpoint
 * ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler}). Stateless.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
interface WidgetDataService {

    /**
     * Fetch this widget's data for the current request.
     *
     * @param PluginPage|NULL $widget widget instance (for per-instance params e.g. pagination), or null
     * @return array|NULL payload whose keys map to Page setters per the registry entry, or null when the API did not
     *                    answer and the widget must not be marked as loaded in `page.loadedData` (saveForLater)
     */
    public function fetchForWidget(?PluginPage $widget): ?array;
}
