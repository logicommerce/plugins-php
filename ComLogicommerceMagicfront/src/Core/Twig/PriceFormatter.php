<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use NumberFormatter;

/**
 * Renders a numeric price into the structured-span HTML used by featured-product
 * widgets:
 *
 *   <span class="price">
 *     [<span class="currencySymbol">€</span>]
 *     <span class="integerPrice" content="14.99">14</span>
 *     [<span class="decimalPrice">,99</span>]
 *     [<span class="currencySymbol">€</span>]
 *   </span>
 *
 * Mirrors fwk's `outputHtmlCurrency()` output byte-for-byte (currency-first
 * detection, decimal trimming, separator handling) but uses only PHP built-in
 * `NumberFormatter` + values supplied by {@see ContextBuilder}. That makes it
 * safe to register as a Twig function in both the storefront fwk renderer AND
 * the docker template-renderer (which has no fwk/sdk available).
 *
 * No silent fallbacks: a malformed locale or unparseable formatted string
 * raises a RuntimeException instead of returning empty HTML, so data bugs
 * surface instead of producing blank prices in the UI.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class PriceFormatter {

    /** ICU pattern marker that means the currency symbol comes first. */
    private const PATTERN_SYMBOL_FIRST = '¤';

    public static function format(float $value, ContextBuilder $ctx): string {
        // Preview-mode short-circuit. The docker template-renderer runs in
        // preview mode and has no Session / Application to derive locale +
        // currency from, and the Java caller doesn't forward them in the
        // /renderWidget payload. Rather than fabricate a specific currency or
        // 422 every editor preview, format the ACTUAL value passed in (the
        // widget template's demo fallback — e.g. 14.99 — flows through here)
        // with a neutral comma-decimal layout and the locale-agnostic generic
        // currency sign `¤` (U+00A4). So the preview shows the demo amount
        // "14,99 ¤" rather than a meaningless 0, while never inventing a real
        // currency. The leading space stays inside the preview symbol so the
        // shared renderSpans (used by real prices, which match fwk's tight
        // spacing) is untouched. Storefront rendering is NEVER preview, so
        // customer-facing pages always go through the full locale-aware path.
        if ($ctx->previewMode) {
            [$max] = self::decimalLimits();
            $display = number_format($value, $max, ',', '');
            [$integer, $decimal] = array_pad(explode(',', $display, 2), 2, null);
            return self::renderSpans(
                ['integer' => $integer, 'decimal' => $decimal, 'decimalSymbol' => ',', 'symbolFirst' => false],
                number_format($value, $max, '.', ''),
                ' ¤'
            );
        }

        if ($ctx->locale === null || $ctx->currencyCode === null) {
            throw new \RuntimeException(
                'PriceFormatter: mff_price requires `locale` and `currencyCode` on ContextBuilder. '
                . 'Storefront callers get these from fwk Session; the docker template-renderer '
                . 'must forward both in the /renderWidget payload.'
            );
        }

        [$max, $min] = self::decimalLimits();
        $value = round($value, $max);

        $fmt        = self::buildCurrencyFormatter($ctx, $max, $min);
        $contentFmt = self::buildContentFormatter($max);

        $parts = self::parseFormattedValue($fmt, $value, $ctx, $min);

        return self::renderSpans(
            $parts,
            \numfmt_format($contentFmt, $value),
            $ctx->currencySymbolOverride ?? $parts['icuSymbol']
        );
    }

    /** @return array{0:int,1:int} [max, min] decimal digit limits. */
    private static function decimalLimits(): array {
        return [
            defined('CURRENCY_DECIMALS_MAX_LENGTH') ? CURRENCY_DECIMALS_MAX_LENGTH : 2,
            defined('CURRENCY_DECIMALS_MIN_LENGTH') ? CURRENCY_DECIMALS_MIN_LENGTH : 2,
        ];
    }

    private static function buildCurrencyFormatter(ContextBuilder $ctx, int $max, int $min): NumberFormatter {
        $fmt = \numfmt_create($ctx->locale . '@currency=' . $ctx->currencyCode, NumberFormatter::CURRENCY);
        if ($fmt === false) {
            throw new \RuntimeException(
                "PriceFormatter: numfmt_create failed for locale='{$ctx->locale}' currency='{$ctx->currencyCode}'."
            );
        }
        \numfmt_set_attribute($fmt, NumberFormatter::MAX_FRACTION_DIGITS, $max);
        \numfmt_set_attribute($fmt, NumberFormatter::MIN_FRACTION_DIGITS, $min);
        return $fmt;
    }

    /**
     * EN-decimal formatter used for the `content=""` attribute, so analytics /
     * structured-data consumers can parse the price independent of locale.
     */
    private static function buildContentFormatter(int $max): NumberFormatter {
        $fmt = \numfmt_create('EN', NumberFormatter::PATTERN_DECIMAL);
        \numfmt_set_pattern($fmt, '#0.' . str_repeat('0', $max));
        return $fmt;
    }

    /**
     * Run the formatter and split the result into the pieces a structured-span
     * renderer needs.
     *
     * @return array{integer:string, decimal:?string, decimalSymbol:string, icuSymbol:string, symbolFirst:bool}
     */
    private static function parseFormattedValue(NumberFormatter $fmt, float $value, ContextBuilder $ctx, int $minDecimals): array {
        $formatted = \numfmt_format_currency($fmt, $value, $ctx->currencyCode);
        if ($formatted === false) {
            throw new \RuntimeException(
                "PriceFormatter: numfmt_format_currency failed for value={$value} currency='{$ctx->currencyCode}'."
            );
        }

        $decimalSymbol = \numfmt_get_symbol($fmt, NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
        $icuSymbol     = \numfmt_get_symbol($fmt, NumberFormatter::CURRENCY_SYMBOL);
        $symbolFirst   = substr(\numfmt_get_pattern($fmt), 0, 2) === self::PATTERN_SYMBOL_FIRST;

        // Strip the ICU symbol + non-breaking spaces, then split on the locale decimal.
        $numeric = trim(preg_replace('/\xA0/u', ' ', str_replace($icuSymbol, '', $formatted)));
        $parts   = explode($decimalSymbol, $numeric, 2);
        $integer = $parts[0] ?? '';
        if ($integer === '') {
            throw new \RuntimeException(
                "PriceFormatter: failed to parse integer portion from '{$formatted}' (locale='{$ctx->locale}' currency='{$ctx->currencyCode}')."
            );
        }

        return [
            'integer'       => $integer,
            'decimal'       => isset($parts[1]) ? self::trimTrailingZeros($parts[1], $minDecimals) : null,
            'decimalSymbol' => $decimalSymbol,
            'icuSymbol'     => $icuSymbol,
            'symbolFirst'   => $symbolFirst,
        ];
    }

    private static function trimTrailingZeros(string $decimal, int $minLength): string {
        while (strlen($decimal) > $minLength && substr($decimal, -1) === '0') {
            $decimal = substr($decimal, 0, -1);
        }
        return $decimal;
    }

    private static function renderSpans(array $parts, string $rawContent, string $renderedSymbol): string {
        // Empty symbol → no currencySymbol span at all (preview placeholder
        // uses this to render bare digits). Real prices always have a symbol.
        $symbolSpan = $renderedSymbol !== ''
            ? '<span class="currencySymbol">' . self::escape($renderedSymbol) . '</span>'
            : '';

        $out = '<span class="price">';
        if ($parts['symbolFirst']) {
            $out .= $symbolSpan;
        }
        $out .= '<span class="integerPrice" content="' . self::escape($rawContent) . '">'
              . self::escape($parts['integer']) . '</span>';
        if ($parts['decimal'] !== null && $parts['decimal'] !== '') {
            $out .= '<span class="decimalPrice">'
                  . self::escape($parts['decimalSymbol'] . $parts['decimal']) . '</span>';
        }
        if (!$parts['symbolFirst']) {
            $out .= $symbolSpan;
        }
        return $out . '</span>';
    }

    private static function escape(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
