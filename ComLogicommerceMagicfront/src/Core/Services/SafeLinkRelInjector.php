<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

/**
 * Production-side counterpart of {@code docker/template-renderer/transforms/
 * SafeLinkRelInjector}. Enforces {@code rel="noopener noreferrer"} on every
 * {@code <a target="_blank">} emitted by a widget in the production storefront.
 *
 * <p>The preview renderer (Docker) calls its own copy of this utility after
 * {@code Twig::render('widget', …)}. Production rendering runs through the
 * LC framework's Twig macros ({@code widgets.html.twig} → {@code template_from_string()}),
 * so there is no single post-render hook in the plugin today — the intended
 * wiring is a framework-level output filter or a Twig extension that calls
 * {@link inject()} on the rendered HTML of each widget. This utility is kept
 * byte-identical to the Docker one so the two sides converge the moment the
 * wiring lands.
 *
 * <p>Until the framework hook exists, widget templates that need the safety
 * guarantee must continue to emit {@code rel="noopener noreferrer"} inline
 * (see {@code linkBar.json} templateHtml conditional on target=_blank).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class SafeLinkRelInjector {

    /**
     * Single-pass regex injection. Runs once on the fully-rendered widget HTML.
     * The pattern is non-greedy on the opening-tag body and tolerant to both
     * single- and double-quoted {@code target} values; unquoted forms
     * ({@code target=_blank}) are intentionally ignored because they are not
     * emitted by any current widget template and sub-optimal HTML anyway.
     *
     * @param string $html Fully-rendered widget HTML (post-Twig).
     * @return string Same HTML with {@code rel="noopener noreferrer"} added
     *     to any {@code <a target="_blank">} that lacked a rel attribute.
     */
    public static function inject(string $html): string {
        if ($html === '' || !str_contains($html, '_blank')) {
            return $html;
        }
        return preg_replace_callback(
            '/<a\b([^>]*\btarget\s*=\s*["\']_blank["\'][^>]*)>/i',
            static function (array $m): string {
                $attrs = $m[1];
                if (preg_match('/\brel\s*=\s*["\']/i', $attrs) === 1) {
                    return '<a' . $attrs . '>';
                }
                return '<a' . $attrs . ' rel="noopener noreferrer">';
            },
            $html
        ) ?? $html;
    }
}
