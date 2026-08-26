<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * Raw request signals for the storefront. Deliberately NOT render modes: "is this the canvas",
 * "is this a preview" and everything derived from a token live in ONE place,
 * {@see RenderMode}.
 *
 * Pulled out of MagicfrontTrait so the trait stays focused on controller
 * lifecycle hooks and the side concerns are reusable and testable on their
 * own.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class MagicfrontUtils {

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
    public static function isIframeRequest(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return strtolower((string) (REQUEST_HEADERS['SEC-FETCH-DEST'] ?? '')) === 'iframe';
    }

    /**
     * Detect a MagicFront content-only partial render: an AJAX request (e.g. a userPanel tab
     * swap) that wants ONLY the page's widget body, skipping chrome, the category nav and the
     * asset bundle. Signalled by the `X-MFF-Content-Only: 1` request header the widget JS sends;
     * a header (not a query param) so no Varnish query-whitelist entry is needed and account
     * pages stay uncached. Strictly additive: absent header → normal full render, byte-identical.
     */
    public static function isContentOnlyRequest(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return (string) (REQUEST_HEADERS['X-MFF-CONTENT-ONLY'] ?? '') === '1';
    }
}
