<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * The render scenario a storefront request belongs to. Single source of truth: derived from the
 * raw signals in {@see MagicfrontUtils} plus {@see MagicfrontToken}, never re-tested elsewhere.
 *
 * Three scenarios exist — a real visitor (neither is true), the standalone preview tab (preview
 * only) and THE editor canvas (both). The pair is deliberately asymmetric: the canvas is the only
 * place mock data may appear, while preview additionally covers "not a real customer, so serve
 * the freshest unpublished content and skip the caches".
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class RenderMode {

    /**
     * THE editor canvas: rendered inside an iframe AND carrying a token.
     *
     * The token comes from {@see MagicfrontToken::getToken()} (cookie first, URL as fallback)
     * because the canvas drops the URL param on internal navigation while the cookie survives.
     */
    public static function isCanvasMode(): bool {
        return MagicfrontUtils::isIframeRequest() && MagicfrontToken::getToken() !== null;
    }

    /**
     * The canvas OR the standalone preview tab (`?mfToken=…` in a top-level window).
     *
     * Only the URL param counts here: inside the canvas the iframe half of the OR is already true,
     * and a lingering cookie must not turn a plain storefront tab into a preview.
     */
    public static function isPreviewMode(): bool {
        return MagicfrontUtils::isIframeRequest() || !empty($_GET[MagicfrontToken::MF_TOKEN]);
    }
}
