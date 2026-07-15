<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * Static utilities related to the storefront/canvas request. Currently
 * exposes a single detector: whether the page is rendered inside the
 * editor's canvas iframe.
 *
 * Pulled out of MagicfrontTrait so the trait stays focused on controller
 * lifecycle hooks and the side concerns are reusable and testable on their
 * own.
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
}
