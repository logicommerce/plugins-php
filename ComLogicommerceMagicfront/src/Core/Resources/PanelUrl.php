<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * The active panel/section for panel widgets (userPanel and future merchant-defined custom panels),
 * carried on the URL as `?mfPanel=<sectionId>`.
 *
 * Single source of truth for that param name so both the request-filter declaration (which lands the
 * value on the `requestParams` Twig global) and any PHP reader agree. The widget is data-driven: a tab
 * is a plain `?mfPanel=<id>` link and the template renders only the matching section server-side (one
 * instance, no duplicate ids, LC binds it fresh on the normal storefront load — no AJAX, no token).
 * Nothing here hardcodes which sections exist; panels live in the widget schema.
 *
 * NOTE (infra): `mfPanel` must be on the Varnish query-param whitelist, otherwise the cache layer strips
 * it before PHP and the section never arrives. It is deliberately NOT the FWK routing `path` param —
 * `?path=` is appended by the `.htaccess` `[QSA]` rewrite and PHP's last-wins duplicate clobbers the real
 * route → 404.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class PanelUrl {

    /** URL query param carrying the active section id (must be Varnish-whitelisted). */
    public const PARAM = 'mfPanel';

    /** Allowed section-id shape — plain id characters, so a crafted value can't inject anything. */
    private const ID_PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * The requested section id, sanitised, or $default when absent/invalid. Reads $_GET directly so the
     * helper stays usable from either renderer without an FWK dependency; widgets normally read the
     * `requestParams` global instead and only need {@see self::PARAM}.
     */
    public static function activeSection(string $default = ''): string {
        $raw = $_GET[self::PARAM] ?? '';
        if (!is_string($raw) || preg_match(self::ID_PATTERN, $raw) !== 1) {
            return $default;
        }
        return $raw;
    }
}
