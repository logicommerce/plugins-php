<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;

/**
 * Static utilities related to the storefront/canvas request: detect whether
 * the page is rendered inside the editor's canvas iframe, and build the
 * plugin-route URL that storefront `<link>` / `<script>` tags point at.
 *
 * Pulled out of MagicfrontTrait so the trait stays focused on controller
 * lifecycle hooks (init, batch, data) and the side concerns are reusable
 * and testable on their own.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class MagicfrontUtils {

    /**
     * Detect whether this storefront request is rendered inside an iframe
     * (typically the magicfront editor's canvas preview).
     *
     * Browser-emitted `Sec-Fetch-Dest: iframe` is the signal — automatic,
     * covers internal navigation inside the canvas, doesn't depend on the
     * editor adding URL params or cookies. Storefronts deny third-party
     * embedding via X-Frame-Options, so in practice an iframe load means
     * the canvas editor opened it.
     */
    public static function isCanvasMode(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return strtolower((string) (REQUEST_HEADERS['SEC-FETCH-DEST'] ?? '')) === 'iframe';
    }

    /**
     * Build the per-page CSS or JS plugin-route URL embedded in storefront
     * `<link>` / `<script>` tags. `$type` is one of
     * FunctionType::CUSTOMIZE_CSS / CUSTOMIZE_JS — the handler dispatches
     * on that param.
     */
    public static function storefrontUrl(string $type, string $pageId, string $language): string {
        return sprintf(
            '/%s/resources/plugin_route/%s?type=%s&page=%s&language=%s',
            INTERNAL_PREFIX,
            ComLogicommerceMagicfrontController::PLUGIN_MODULE,
            $type,
            urlencode($pageId),
            urlencode($language)
        );
    }
}
