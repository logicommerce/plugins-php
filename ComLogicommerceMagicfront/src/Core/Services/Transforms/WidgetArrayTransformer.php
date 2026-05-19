<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

/**
 * Self-contained array-only widget → page transformer.
 *
 * Takes a widget DTO as a plain associative array (the shape Magic Front API
 * sends — `id`, `widgetTemplateId`, `propertyValues`, `styleValues`,
 * `children`, etc.) and returns a page-shaped associative array consumed by
 * the widget Twig templates and macros.
 *
 *   Magic Front API structure            Page-compatible structure
 *   id (string UUID)              →      id + draftId (both string UUID)
 *   widgetTemplateId              →      type + customType
 *   orderIndex                    →      position
 *   propertyValues + styleValues  →      moduleSettings + customTagValues
 *   children                      →      subpages (recursive)
 *
 * No FWK / SDK dependencies — only Plugin-local enums and PHP built-ins.
 * This is what lets the docker template-renderer mount this file without
 * pulling in the rest of the framework, and what lets {@see
 * \Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer}
 * reuse the exact same normalization rules as the preview renderer.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services\Transforms
 */
class WidgetArrayTransformer {

    /**
     * Transform a widget array into a page-compatible array.
     */
    public static function transformToArray(array $widget): array {
        $type      = $widget['widgetTemplateId'] ?? '';
        $allValues = array_merge($widget['propertyValues'] ?? [], $widget['styleValues'] ?? []);
        $children  = $widget['children'] ?? [];
        $processed = self::processPropertyValues($type, $allValues, count($children));

        return [
            'id'              => $widget['id'] ?? '',
            'draftId'         => $widget['id'] ?? '',
            'type'            => $type,
            'customType'      => $type,
            'position'        => (int) ($widget['orderIndex'] ?? 0),
            'pageType'        => 'CUSTOM',
            'active'          => true,
            'moduleSettings'  => $processed['moduleSettings'],
            'customTagValues' => $processed['customTags'],
            'language'        => $processed['language'],
            'subpages'        => self::transformChildrenToArrays($children),
            'slotId'          => $widget['slotId'] ?? null,
            'slotPermissions' => $widget['slotPermissions'] ?? null,
        ];
    }

    // ─── Property processing ──────────────────────────────────────────────────

    /**
     * Single-pass: builds moduleSettings + customTagValues simultaneously.
     *
     * @return array{moduleSettings: array, customTags: array, language: array}
     */
    private static function processPropertyValues(string $type, array $propertyValues, int $childrenCount = 0): array {
        $moduleSettings = [];
        $customTags     = [];

        foreach ($propertyValues as $pv) {
            $entry = self::normalizePropertyEntry($pv);
            if (!$entry['enabled'] || $entry['propertyId'] === '') {
                continue;
            }

            $propId    = $entry['propertyId'];
            $value     = $entry['value'];
            $elementId = $entry['elementId'];

            $moduleSettings[$propId] = $value;

            if ($elementId !== '') {
                $moduleSettings[$elementId . '.' . $propId] = $value;
            }

            // Top-level (unprefixed) property IDs get a type-prefixed alias for
            // template compatibility.
            if ($type !== '' && strpos($propId, '.') === false && !preg_match('/^[A-Z_]+\./', $propId)) {
                $moduleSettings[$type . '.' . $propId] = $value;
                if ($elementId !== '') {
                    $moduleSettings[$type . '.' . $elementId . '.' . $propId] = $value;
                }
            }

            $customTags[] = [
                'customTagPId' => $propId,
                'value'        => is_string($value)
                    ? $value
                    : (is_array($value) ? (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '') : (string) $value),
            ];
        }

        // Smart default: column count falls back to actual child count.
        if ($type === 'columns' && $childrenCount > 0) {
            $moduleSettings['count']         ??= $childrenCount;
            $moduleSettings['columns.count'] ??= $childrenCount;
        }

        return ['moduleSettings' => $moduleSettings, 'customTags' => $customTags, 'language' => []];
    }

    /**
     * Recursively convert child widgets to page-compatible arrays.
     */
    private static function transformChildrenToArrays(array $children): array {
        $subpages = [];

        foreach ($children as $child) {
            $childData = is_array($child) ? $child : null;
            if ($childData === null) {
                continue;
            }

            $childType = $childData['widgetTemplateId'] ?? '';
            $allValues = array_merge($childData['propertyValues'] ?? [], $childData['styleValues'] ?? []);
            $processed = self::processPropertyValues($childType, $allValues);

            $subpages[] = [
                'id'              => 0,
                'draftId'         => $childData['id'] ?? '',
                'customType'      => $childType,
                'position'        => $childData['orderIndex'] ?? 0,
                'pageType'        => 'CUSTOM',
                'active'          => true,
                'customTagValues' => $processed['customTags'],
                'subpages'        => self::transformChildrenToArrays($childData['children'] ?? []),
                'language'        => $processed['language'],
                'moduleSettings'  => $processed['moduleSettings'],
                'slotId'          => $childData['slotId'] ?? null,
                'slotPermissions' => $childData['slotPermissions'] ?? null,
            ];
        }

        return $subpages;
    }

    // ─── Normalization ────────────────────────────────────────────────────────

    /**
     * Normalize a single property value entry. API can send `propertyId`
     * (PropertyValueDTO) or `styleId` (StyleValueDTO) as the identifier.
     *
     * @return array{propertyId: string, value: mixed, elementId: string, enabled: bool}
     */
    private static function normalizePropertyEntry(array $propertyValue): array {
        $propId    = $propertyValue['propertyId'] ?? $propertyValue['styleId'] ?? '';
        $elementId = $propertyValue['elementId'] ?? '';
        $enabled   = array_key_exists('enabled', $propertyValue) ? (bool) $propertyValue['enabled'] : true;

        return [
            'propertyId' => $propId,
            'value'      => self::normalizeValue($propertyValue['value'] ?? '', $propertyValue['unit'] ?? null),
            'elementId'  => is_string($elementId) ? $elementId : '',
            'enabled'    => $enabled,
        ];
    }

    /**
     * Normalize a raw API value to a usable scalar or string.
     *
     * Handles:
     *   - Flat dimension (Java DimensionStyleValueDTO shape):
     *       value scalar + sibling `unit` field → "5px"
     *   - Nested dimension struct: `{"value": 5, "unit": "px"}` → "5px"
     *   - Localized array: `[{"value": "Hello"}, ...]` → "Hello"
     *   - Other scalars: returned as-is.
     */
    private static function normalizeValue(mixed $value, mixed $unit = null): mixed {
        // Flat numeric value with sibling `unit` (Java DimensionStyleValueDTO).
        if ((is_int($value) || is_float($value) || is_numeric($value)) && is_string($unit) && $unit !== '') {
            return self::formatNumber($value) . $unit;
        }

        if (!is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return '';
        }

        // Nested dimension struct: {"value": 5, "unit": "px"} → "5px"
        if (count($value) === 2 && array_key_exists('value', $value) && array_key_exists('unit', $value)) {
            $raw = $value['value'];
            $u   = $value['unit'];
            if ((is_int($raw) || is_float($raw) || is_numeric($raw)) && is_string($u)) {
                return $u === '' ? self::formatNumber($raw) : self::formatNumber($raw) . $u;
            }
        }

        // Localized array: [{"value": "Hello"}, {"language": "es", "value": "Hola"}, ...]
        if (isset($value[0]) && is_array($value[0]) && array_key_exists('value', $value[0])) {
            $first = $value[0]['value'] ?? '';
            return is_string($first) ? $first : (string) $first;
        }

        return $value;
    }

    private static function formatNumber(mixed $n): string {
        if (is_int($n)) {
            return (string) $n;
        }
        if (is_float($n)) {
            return rtrim(rtrim(sprintf('%.6f', $n), '0'), '.');
        }
        return (string) $n;
    }
}
