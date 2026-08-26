<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Shared helper for the generic `pagination` slot widget. Pagination CONFIG (perPage / pagesToShow) lives
 * on the embedded `pagination` widget — the single source — so any host LIST widget's carrier reads the
 * API page size from ITS pagination slot child instead of carrying its own perPage. Keep this generic:
 * every future paginable list widget (whose API actually paginates — see the server-side pagination rule)
 * embeds a `pagination` slot and reads perPage through here.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class PaginationHelper {

    /** The API page size configured on the host widget's embedded `pagination` slot child, or null → SDK default. */
    public static function perPage(?PluginPage $host): ?int {
        if ($host === null) {
            return null;
        }
        $pager = WidgetLocator::findByType(new ElementCollection(['items' => $host->getSubpages()]), 'pagination');
        $value = $pager !== null ? ($pager->getModuleSettings()['perPage'] ?? null) : null;
        return is_numeric($value) ? (int) $value : null;
    }
}
