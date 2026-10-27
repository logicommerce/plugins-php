<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;

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

    public const MFF_LANG = 'mff_lang';

    /** The chrome region a render is scoped to ({@see self::chromeOnlyRegion()}). */
    public const MFF_CHROME_ONLY = 'mff_chrome_only';

    /** The «new header» draft a chrome-scoped preview paints ({@see self::chromeDraftId()}). */
    public const MFF_CHROME_DRAFT = 'mff_chrome_draft';

    private const STUDIO_STORE_PREFIX = 'store:';

    private const STUDIO_DRAFT_PREFIX = 'draft:';

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
    /**
     * A per-widget refresh the editor canvas fetches from INSIDE the iframe (`getWidget`, `customizeCssJs`):
     * `Sec-Fetch-Dest` is `empty` there, not `iframe`, so without this header the refresh rendered as the
     * storefront and dropped the editor-only markup (`mff_isCanvasMode()`). The canvas sends
     * `X-MFF-Canvas: 1` on those requests; the token check stays in RenderMode.
     */
    public static function isCanvasFetch(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return (string) (REQUEST_HEADERS['X-MFF-CANVAS'] ?? '') === '1';
    }

    public static function isIframeRequest(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return strtolower((string) (REQUEST_HEADERS['SEC-FETCH-DEST'] ?? '')) === 'iframe';
    }

    /**
     * Whether a script of the page asked for it (XMLHttpRequest / fetch) instead of the browser navigating to it.
     * LogiCommerce's «view order» popup (`LC.dataEvents.viewDocumentPopup`, lc.plugins.js) loads the order's address this
     * way and keeps the `div.document` of the answer: redirected to the account page, the popup stayed blank (F-884).
     *
     * The browser says it in `Sec-Fetch-Mode`: `navigate` for a navigation (the editor canvas too, which is
     * `Sec-Fetch-Dest: iframe`), `cors` or `same-origin` for a script that reads the answer. Any other mode (`no-cors`
     * is an image, a stylesheet, a prefetch) is not one. A browser that does not send it (before Chrome 76, Firefox 90,
     * Safari 16.4) falls back to jQuery's `X-Requested-With: XMLHttpRequest`; the popup itself does not send that one
     * (`crossDomain: true`). Without either, it is a navigation, as before.
     */
    public static function isScriptRequest(): bool {
        if (!defined('REQUEST_HEADERS')) {
            return false;
        }
        return self::isScriptRequestFrom(REQUEST_HEADERS);
    }

    /**
     * Pure core of {@see isScriptRequest()}, over the request headers keyed in upper case, as the FWK's
     * `REQUEST_HEADERS` (`array_change_key_case(getallheaders(), CASE_UPPER)`).
     *
     * @param array<string, mixed> $headers
     */
    public static function isScriptRequestFrom(array $headers): bool {
        $mode = $headers['SEC-FETCH-MODE'] ?? null;
        if (is_string($mode) && trim($mode) !== '') {
            return in_array(strtolower(trim($mode)), ['cors', 'same-origin'], true);
        }
        $requestedWith = $headers['X-REQUESTED-WITH'] ?? null;
        return is_string($requestedWith) && strcasecmp(trim($requestedWith), 'XMLHttpRequest') === 0;
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

    /**
     * The chrome region a render must be scoped to, or null for a normal full page. Signalled by
     * `?mff_chrome_only=header|footer|accountPanel|basketPanel|mobileMenuPanel` — a query param and
     * not a header because the editor loads this as an iframe `src` and cannot set headers. Only the
     * MagicFront editor is ever scoped: without a preview token the param is ignored outright, so no
     * visitor (and no cache entry) can reach a headless page by guessing the URL.
     *
     * <p>The three panels are scoped too: they are overlays the visitor opens, so inside a whole shop
     * page the merchant could not see the one being edited at all.</p>
     */
    public static function chromeOnlyRegion(): ?string {
        if (MagicfrontToken::getToken() === null) {
            return null;
        }
        return ChromeKind::tryFrom(RequestQuery::value(self::MFF_CHROME_ONLY, RequestQuery::exact()) ?? '')?->value;
    }

    /**
     * `?mff_chrome_draft=<id>` — the «new header» draft (E20) the editor asks to SEE in place of the document of its
     * kind, while the merchant decides whether to use it. Only on a chrome-scoped preview (`mff_chrome_only`), only
     * with a preview token, and only an id-shaped value: a visitor can never reach it, and a draft is read-only on the
     * server. Null otherwise, so the region paints its own document as before.
     */
    public static function chromeDraftId(): ?string {
        $draft = RequestQuery::value(self::MFF_CHROME_DRAFT, RequestQuery::exact());
        return self::chromeDraftIdFrom(self::chromeOnlyRegion(), $draft);
    }

    /** Pure core of {@see chromeDraftId()}: the scoped region (null without a token) and the query value as read. */
    public static function chromeDraftIdFrom(?string $chromeOnlyRegion, mixed $raw): ?string {
        if ($chromeOnlyRegion === null || !is_string($raw)) {
            return null;
        }
        return preg_match('/^[A-Za-z0-9-]{1,64}$/', $raw) === 1 ? $raw : null;
    }

    public static function isStudioDocumentRef(string $pageId): bool {
        return str_starts_with($pageId, self::STUDIO_STORE_PREFIX) || str_starts_with($pageId, self::STUDIO_DRAFT_PREFIX);
    }

    /**
     * The locale the MFF widget content is fetched in. The route language rules the storefront; the
     * editor may override it with `?mff_lang=xx` — the gallery previews a template in English while
     * the shop's own route stays Spanish. The override is honoured ONLY behind a preview token, so a
     * visitor cannot force a locale (nor a cache entry) by guessing the URL. Single reader of the
     * param: the trait and the Twig initializer both resolve through here, so a render and the fetch
     * it feeds can never disagree on the locale.
     */
    public static function contentLanguage(?string $routeLanguage): string {
        $override = self::requestedLanguage();
        if ($override !== null && MagicfrontToken::getToken() !== null) {
            return $override;
        }
        return (string) $routeLanguage;
    }

    /**
     * The language the editor asked for with `?mff_lang=xx` (ISO 639-1, lower case, as the FWK filters language
     * codes), or null. Raw signal: whether a preview token lets it rule is the caller's business.
     */
    public static function requestedLanguage(): ?string {
        return RequestQuery::value(self::MFF_LANG, FilterInputFactory::getLanguageCodeParameter()[Parameters::LANGUAGE_CODE]);
    }

    /**
     * Where a preview must go so the WHOLE page is in the language the editor asked for (`mff_lang`), or null to
     * stay. `mff_lang` only switches the MagicFront content ({@see contentLanguage}); the theme, the header menu and
     * `<html lang>` follow the route language, so `/?mff_lang=en` painted English widgets inside a Spanish store.
     * The target is the store's own address of this page in that language (the route's available languages, the
     * same list its language selector uses) plus this request's query, so a prefix, a domain per language and a
     * default language without prefix all work, and the token and the page travel along.
     *
     * No loop: on arrival the route language is the one asked for. A language the route does not offer stays
     * where it is (content in `mff_lang`, as before). Without a preview token nothing changes.
     *
     * @param array $languageUrls language code => url of this route in that language
     * @param string $queryString the raw query of this request ($_SERVER['QUERY_STRING'])
     */
    public static function languageRedirectUrl(?string $override, bool $hasToken, ?string $routeLanguage, array $languageUrls,
        string $queryString = ''): ?string {
        $override = strtolower(trim((string) $override));
        if (!$hasToken || $override === '' || $override === strtolower((string) $routeLanguage)) {
            return null;
        }
        foreach ($languageUrls as $code => $url) {
            if (strtolower((string) $code) === $override && is_string($url) && $url !== '') {
                return self::withRequestQuery($url, $queryString);
            }
        }
        return null;
    }

    /**
     * The language url with this request's query: the SDK hands the url WITHOUT it ({@see LanguageRoute::getUrl} cuts
     * at `?`), and without the token, `page` and `mff_*` the frame would land on the plain store. The query is copied
     * raw, so the token is not re-encoded, minus `path`, the route the store's rewrite adds (`index.php?path=$1`).
     */
    private static function withRequestQuery(string $url, string $queryString): string {
        $kept = [];
        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '' || urldecode(explode('=', $pair, 2)[0]) === 'path') {
                continue;
            }
            $kept[] = $pair;
        }
        if ($kept === []) {
            return $url;
        }
        return $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $kept);
    }
}
