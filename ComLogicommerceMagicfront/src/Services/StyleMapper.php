<?php

namespace Plugins\ComLogicommerceMagicfront\Services;

/**
 * Style mapping utilities for converting styleValues to CSS declarations.
 * Port of dcseditor/src/editor/utils/styleMapping.ts
 *
 * @package Plugins\ComLogicommerceMagicfront\Services
 */
class StyleMapper {

    /** CSS properties that require "px" unit */
    private const DIMENSION_PROPS = [
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'border-width', 'border-radius', 'width', 'height',
        'top', 'right', 'bottom', 'left',
        'gap', 'row-gap', 'column-gap',
        'font-size', 'line-height', 'letter-spacing'
    ];

    /**
     * Convert styleTagPId to CSS property name
     *
     * @param string $styleTagPId e.g., "SHADOW.offsetX", "padding-top"
     * @return string CSS property, e.g., "box-shadow", "padding-top"
     */
    public static function propertyIdToCss(string $styleTagPId): string {
        if (strpos($styleTagPId, 'SHADOW.') === 0) {
            return 'box-shadow';
        }

        // The propertyId is already a valid CSS property name
        return $styleTagPId;
    }

    /**
     * Format value for CSS (add "px" to bare numbers if needed)
     *
     * @param string $cssProperty CSS property name
     * @param mixed $value Raw value
     * @return string Formatted CSS value
     */
    public static function toCssValue(string $cssProperty, $value): string {
        $str = strval($value);

        if ($str === '') {
            return '';
        }

        // Add "px" to bare numbers for dimension properties
        if (in_array($cssProperty, self::DIMENSION_PROPS, true)) {
            if (preg_match('/^-?\d+(\.\d+)?$/', $str)) {
                return $str . 'px';
            }
        }

        return $str;
    }

    /**
     * Build box-shadow from SHADOW.* styleValues
     *
     * @param array $shadowValues Associative array of SHADOW.* values
     * @return string Complete box-shadow CSS value
     */
    public static function buildBoxShadow(array $shadowValues): string {
        $get = function($part, $default) use ($shadowValues) {
            $key = "SHADOW.{$part}";
            $value = $shadowValues[$key] ?? $default;

            if ($part === 'color') {
                return $value;
            }

            // Add "px" to numeric values
            if (preg_match('/^-?\d+(\.\d+)?$/', strval($value))) {
                return $value . 'px';
            }

            return $value;
        };

        return sprintf('%s %s %s %s %s',
            $get('offsetX', '0'),
            $get('offsetY', '0'),
            $get('blur', '0'),
            $get('spread', '0'),
            $get('color', 'rgba(0,0,0,0.2)')
        );
    }

    /**
     * Group styleValues by type for efficient processing
     *
     * @param array $styleValues Array of styleValue objects
     * @return array Grouped by category
     */
    public static function groupStyleValues(array $styleValues): array {
        $grouped = [
            'simple' => [],
            'spacing' => [],
            'border' => [],
            'shadow' => []
        ];

        foreach ($styleValues as $style) {
            $pid = $style['styleTagPId'] ?? '';

            if ($pid === '') {
                continue;
            }

            if (strpos($pid, 'SPACING.') === 0) {
                $grouped['spacing'][$pid] = $style['value'] ?? '';
            } elseif (strpos($pid, 'BORDER.') === 0) {
                $grouped['border'][$pid] = $style['value'] ?? '';
            } elseif (strpos($pid, 'SHADOW.') === 0) {
                $grouped['shadow'][$pid] = $style['value'] ?? '';
            } else {
                $grouped['simple'][] = $style;
            }
        }

        return $grouped;
    }

    /**
     * Generate CSS declarations from styleValues
     *
     * @param array $styleValues Array of styleValue objects
     * @return array Associative array of CSS property => value
     */
    public static function generateCssDeclarations(array $styleValues): array {
        $grouped = self::groupStyleValues($styleValues);
        $declarations = [];

        // Process simple properties
        foreach ($grouped['simple'] as $style) {
            $pid = $style['styleTagPId'] ?? '';
            $value = $style['value'] ?? '';

            if ($pid === '' || $value === '') {
                continue;
            }

            $cssProp = self::propertyIdToCss($pid);
            $cssValue = self::toCssValue($cssProp, $value);

            if ($cssValue !== '') {
                $declarations[$cssProp] = $cssValue;
            }
        }

        // Process SPACING.* (convert to padding-*)
        foreach ($grouped['spacing'] as $pid => $value) {
            $parts = explode('.', $pid);
            if (count($parts) !== 2) {
                continue;
            }

            $side = strtolower($parts[1]);
            $cssProp = "padding-{$side}";
            $cssValue = self::toCssValue($cssProp, $value);

            if ($cssValue !== '') {
                $declarations[$cssProp] = $cssValue;
            }
        }

        // Process BORDER.* (combine if possible)
        if (!empty($grouped['border'])) {
            $width = $grouped['border']['BORDER.width'] ?? null;
            $style = $grouped['border']['BORDER.style'] ?? 'solid';
            $color = $grouped['border']['BORDER.color'] ?? '#000';

            if ($width !== null && $width !== '') {
                $declarations['border'] = self::toCssValue('border-width', $width)
                    . ' ' . $style . ' ' . $color;
            }
        }

        // Process SHADOW.* (build box-shadow)
        if (!empty($grouped['shadow'])) {
            $declarations['box-shadow'] = self::buildBoxShadow($grouped['shadow']);
        }

        return $declarations;
    }
}
