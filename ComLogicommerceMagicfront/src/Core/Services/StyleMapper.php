<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

/**
 * Maps widget style values to CSS declarations. Kept byte-identical (except
 * for namespace) with the preview copy at
 * MagicFront\TemplateRenderer\Css\StyleMapper so preview and production
 * emit the same CSS for the same values.
 *
 * In the current contract, widgets declare the CSS property name directly
 * via each style's `cssProperty` field; this mapper just pairs value with
 * unit and combines the multi-part SHADOW.* group into a single box-shadow
 * declaration. Legacy SPACING.* / BORDER.* prefixes are intentionally not
 * handled — no widget in the catalog uses them.
 */
class StyleMapper {

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Generate CSS declarations from an array of styleValue objects.
     *
     * @param  array                    $styleValues    Array of styleValue objects from the API
     * @param  string                   $elementId      Element identifier (e.g. "title", "root")
     * @param  array<string,string>|null $cssPropertyMap styleId → cssProperty from the template
     * @return array  Associative array of CSS property => value
     */
    public static function generateCssDeclarations(
        array $styleValues,
        string $elementId = '',
        ?array $cssPropertyMap = null
    ): array {
        $declarations = [];
        $shadowValues = [];

        foreach ($styleValues as $style) {
            // styleTagPId / styleId is schema polymorphism — API sends one of the two.
            // `unit` is genuinely optional (not every style has a unit).
            $pid   = $style['styleTagPId'] ?? $style['styleId'];
            $value = self::normalizeValue($style['value']);
            $unit  = $style['unit'] ?? '';

            if (empty($pid) || $value === null || $value === '') {
                continue;
            }

            // SHADOW.* must be collected and combined into one box-shadow declaration
            if (str_starts_with($pid, 'SHADOW.')) {
                $shadowValues[$pid] = $value;
                continue;
            }

            // Everything else: API sends the correct cssProperty, just format the value
            $cssProp  = $cssPropertyMap[$pid] ?? $pid;
            $cssValue = self::toCssValue($value, $unit);

            if ($cssValue !== '') {
                $declarations[$cssProp] = $cssValue;
            }
        }

        // Combine SHADOW.* into box-shadow
        if (!empty($shadowValues)) {
            $declarations['box-shadow'] = self::buildBoxShadow($shadowValues);
        }

        return $declarations;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Format a value + unit into a CSS value string.
     */
    private static function toCssValue(mixed $value, string $unit = ''): string {
        $str = (string)$value;
        if ($str === '') {
            return '';
        }
        if (preg_match('/^-?\d+(\.\d+)?$/', $str) && $unit !== '') {
            return $str . $unit;
        }
        return $str;
    }

    /**
     * Build a box-shadow CSS value from SHADOW.* style values. SHADOW.color
     * is routed through sanitizeCssColor to prevent CSS injection via a
     * persisted malformed color; no default color is supplied — if API omits
     * or sends invalid SHADOW.color the shadow renders as invalid CSS and
     * the bug surfaces.
     */
    private static function buildBoxShadow(array $shadowValues): string {
        return implode(' ', [
            self::shadowPart($shadowValues, 'offsetX', '0'),
            self::shadowPart($shadowValues, 'offsetY', '0'),
            self::shadowPart($shadowValues, 'blur',    '0'),
            self::shadowPart($shadowValues, 'spread',  '0'),
            self::sanitizeCssColor((string) $shadowValues['SHADOW.color'], ''),
        ]);
    }

    private static function shadowPart(array $values, string $part, string $default): string {
        $value = (string)($values["SHADOW.{$part}"] ?? $default);
        if ($value === '') {
            $value = $default;
        }
        return preg_match('/^-?\d+(\.\d+)?$/', $value) ? $value . 'px' : $value;
    }

    /**
     * Normalize a raw API value to a scalar string.
     * Handles the {value, unit} dimension struct returned by the API.
     */
    private static function normalizeValue(mixed $value): mixed {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }
        if (!is_array($value)) {
            return $value;
        }
        if (count($value) === 2 && isset($value['value'], $value['unit'])) {
            $raw  = $value['value'];
            $unit = $value['unit'];
            if (is_string($unit)) {
                return $unit === '' ? (string) $raw : (string) $raw . $unit;
            }
        }
        return '';
    }

    /**
     * Accept only well-formed hex, rgb(a), hsl(a) or named-color strings.
     * Falls back to $default when the value doesn't parse — prevents CSS
     * injection via a persisted malformed color.
     */
    private static function sanitizeCssColor(string $value, string $default): string {
        $v = trim($value);
        if (
            preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ||
            preg_match('/^rgba?\s*\([\d\s,%.\/]+\)$/i', $v) ||
            preg_match('/^hsla?\s*\([\d\s,%.\/]+\)$/i', $v) ||
            preg_match('/^[a-zA-Z]+$/', $v)
        ) {
            return $v;
        }
        return $default;
    }
}
