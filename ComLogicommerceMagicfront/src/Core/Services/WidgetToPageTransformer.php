<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use SDK\Core\Dtos\ElementCollection;

/**
 * Transforms WidgetInstance DTOs from the Magic Front API into the Page
 * format expected by PageRelationResolver.
 *
 *   Magic Front API structure            →  Page-compatible structure
 *   id (string)                          →  id (int, 0) + draftId (string)
 *   widgetTemplateId                     →  customType
 *   orderIndex                           →  position
 *   propertyValues + styleValues         →  customTagValues + moduleSettings
 *   children                             →  subpages (plugin Page objects)
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class WidgetToPageTransformer {

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Transform a single WidgetInstance DTO to a Page DTO.
     */
    public static function transformSingle(WidgetInstance $widget): Page {
        return self::widgetToPage($widget);
    }

    /**
     * Transform an ElementCollection of WidgetInstances into an
     * ElementCollection of Pages.
     */
    public static function transform(?ElementCollection $widgets): ?ElementCollection {
        if ($widgets === null) {
            return null;
        }

        $items = $widgets->getItems();
        if (empty($items)) {
            return new ElementCollection(['items' => []]);
        }

        $pages = [];
        foreach ($items as $item) {
            $widget  = $item instanceof WidgetInstance ? $item : new WidgetInstance($item);
            $pages[] = self::widgetToPage($widget);
        }
        return new ElementCollection(['items' => $pages]);
    }

    // ─── Core transformation ──────────────────────────────────────────────────

    private static function widgetToPage(WidgetInstance $widget): Page {
        $allValues = array_merge($widget->getPropertyValues(), $widget->getStyleValues());
        $processed = self::processPropertyValues($widget->getType(), $allValues, count($widget->getChildren()));

        $page = new Page([
            'id'              => 0,
            'customType'      => $widget->getType(),
            'position'        => $widget->getOrderIndex(),
            'pageType'        => 'CUSTOM',
            'active'          => true,
            'customTagValues' => $processed['customTags'],
            'subpages'        => [],
            'language'        => $processed['language'],
            'moduleSettings'  => $processed['moduleSettings'],
        ]);

        $page->setDraftId($widget->getId());
        $page->setSlotId($widget->getSlotId());
        $page->setSlotPermissions($widget->getSlotPermissions());

        $children = $widget->getChildren();
        if (!empty($children)) {
            $page->setFWKSubpages(self::buildChildPages(self::transformChildrenToArrays($children)));
        }
        return $page;
    }

    // ─── Property processing ──────────────────────────────────────────────────

    /**
     * Single-pass processing: builds moduleSettings and customTagValues
     * simultaneously to avoid iterating the same array twice.
     *
     * @return array{moduleSettings: array, customTags: array, language: array}
     */
    private static function processPropertyValues(string $type, array $propertyValues, int $childrenCount = 0): array {
        $moduleSettings = [];
        $customTags     = [];

        foreach ($propertyValues as $pv) {
            $entry = self::normalizePropertyEntry($pv);
            if (!$entry['enabled'] || empty($entry['propertyId'])) {
                continue;
            }
            self::registerModuleSetting($moduleSettings, $type, $entry);
            $customTags[] = self::customTagEntry($entry);
        }

        // Smart default: column count falls back to the actual child count.
        if ($type === 'columns' && $childrenCount > 0) {
            $moduleSettings['count']         ??= $childrenCount;
            $moduleSettings['columns.count'] ??= $childrenCount;
        }

        return ['moduleSettings' => $moduleSettings, 'customTags' => $customTags, 'language' => []];
    }

    /**
     * Register a normalized entry under every moduleSettings key the template
     * might use to look it up (bare, element-scoped, type-scoped, and the
     * fully-qualified combo).
     */
    private static function registerModuleSetting(array &$moduleSettings, string $type, array $entry): void {
        $propId    = $entry['propertyId'];
        $value     = $entry['value'];
        $elementId = $entry['elementId'];

        $moduleSettings[$propId] = $value;

        if ($elementId !== '') {
            $moduleSettings[$elementId . '.' . $propId] = $value;
        }

        // Top-level (unprefixed) property IDs get a type-prefixed alias for
        // template compatibility.
        if ($type !== '' && !str_contains($propId, '.') && !preg_match('/^[A-Z_]+\./', $propId)) {
            $moduleSettings[$type . '.' . $propId] = $value;
            if ($elementId !== '') {
                $moduleSettings[$type . '.' . $elementId . '.' . $propId] = $value;
            }
        }
    }

    /**
     * Build a customTagValues entry for an SDK CustomTagValue DTO (string value).
     *
     * @return array{customTagPId: string, value: string}
     */
    private static function customTagEntry(array $entry): array {
        $value = $entry['value'];
        return [
            'customTagPId' => $entry['propertyId'],
            'value'        => self::stringifyCustomTagValue($value),
        ];
    }

    private static function stringifyCustomTagValue(mixed $value): string {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }
        return (string) $value;
    }

    // ─── Child handling ───────────────────────────────────────────────────────

    /**
     * Build plugin Page objects from child arrays.
     *
     * SDK's PageFactory creates SDK Page objects that lack the moduleSettings
     * property (from MagicfrontPageTrait). Plugin Page objects are used instead
     * so that Twig templates can access `subpage.moduleSettings`.
     *
     * @param  array $childrenArrays  Output of transformChildrenToArrays().
     * @return Page[]
     */
    private static function buildChildPages(array $childrenArrays): array {
        $pages = [];
        foreach ($childrenArrays as $childArray) {
            // transformChildrenToArrays() (below) always populates `subpages`.
            $nestedSubpages         = $childArray['subpages'];
            $childArray['subpages'] = [];

            $childPage = new Page($childArray);
            if (!empty($childArray['pId'])) {
                $childPage->setDraftId($childArray['pId']);
            }
            // slotId / slotPermissions are optional per API schema.
            if (!empty($childArray['slotId'])) {
                $childPage->setSlotId($childArray['slotId']);
            }
            if (!empty($childArray['slotPermissions'])) {
                $childPage->setSlotPermissions($childArray['slotPermissions']);
            }
            if (!empty($nestedSubpages)) {
                $childPage->setFWKSubpages(self::buildChildPages($nestedSubpages));
            }
            $pages[] = $childPage;
        }
        return $pages;
    }

    /**
     * Recursively convert child widgets to page-compatible arrays.
     *
     * Note: SDK's setSubpages() converts these arrays via PageFactory — which
     * creates SDK Page objects without moduleSettings. Use buildChildPages()
     * after this step.
     */
    private static function transformChildrenToArrays(array $children): array {
        $subpages = [];
        foreach ($children as $child) {
            $childData = self::extractChildData($child);
            if ($childData === null) {
                continue;
            }

            $childType = $childData['widgetTemplateId'];
            $allValues = array_merge($childData['propertyValues'], $childData['styleValues']);
            $processed = self::processPropertyValues($childType, $allValues);

            $subpages[] = [
                'id'              => 0,
                'pId'             => $childData['id'],
                'customType'      => $childType,
                'position'        => $childData['orderIndex'],
                'pageType'        => 'CUSTOM',
                'active'          => true,
                'customTagValues' => $processed['customTags'],
                'subpages'        => self::transformChildrenToArrays($childData['children']),
                'language'        => $processed['language'],
                'moduleSettings'  => $processed['moduleSettings'],
                // slotId / slotPermissions are optional per API schema.
                'slotId'          => $childData['slotId'] ?? null,
                'slotPermissions' => $childData['slotPermissions'] ?? null,
            ];
        }
        return $subpages;
    }

    /**
     * Normalize a child entry to an associative array. Accepts WidgetInstance
     * DTOs or already-serialized arrays.
     */
    private static function extractChildData(mixed $child): ?array {
        if ($child instanceof WidgetInstance) {
            return [
                'id'               => $child->getId(),
                'widgetTemplateId' => $child->getWidgetTemplateId(),
                'orderIndex'       => $child->getOrderIndex(),
                'propertyValues'   => $child->getPropertyValues(),
                'styleValues'      => $child->getStyleValues(),
                'children'         => $child->getChildren(),
                'slotId'           => $child->getSlotId(),
                'slotPermissions'  => $child->getSlotPermissions(),
            ];
        }
        return is_array($child) ? $child : null;
    }

    // ─── Normalization ────────────────────────────────────────────────────────

    /**
     * Normalize a single property value entry. API may send `propertyId`
     * (PropertyValueDTO) or `styleId` (StyleValueDTO) as the identifier.
     *
     * @return array{propertyId: string, value: mixed, elementId: string, enabled: bool}
     */
    private static function normalizePropertyEntry(array $propertyValue): array {
        // `elementId` is optional (unscoped properties have none).
        // `propertyId` / `styleId` is schema polymorphism — API sends one of the two.
        return [
            'propertyId' => $propertyValue['propertyId'] ?? $propertyValue['styleId'],
            'value'      => self::normalizeValue($propertyValue['value']),
            'elementId'  => $propertyValue['elementId'] ?? '',
            'enabled'    => array_key_exists('enabled', $propertyValue) ? (bool) $propertyValue['enabled'] : true,
        ];
    }

    /**
     * Normalize a raw API value to a usable scalar or string.
     *
     * Handles:
     *   - Dimension struct: {"value": 5, "unit": "px"} → "5px"
     *   - Localized array:  [{"value": "Hello"}, ...]  → "Hello"
     */
    private static function normalizeValue(mixed $value): mixed {
        if (!is_array($value)) {
            return $value;
        }
        if (empty($value)) {
            return '';
        }
        // Dimension type: {"value": 5, "unit": "px"} → "5px"
        if (count($value) === 2 && isset($value['value'], $value['unit'])) {
            $raw  = $value['value'];
            $unit = $value['unit'];
            if ((is_int($raw) || is_float($raw) || is_numeric($raw)) && is_string($unit)) {
                return $unit === '' ? (string) $raw : (string) $raw . $unit;
            }
        }
        // Localized type: [{"value": "Hello"}, {"language": "es", "value": "Hola"}, ...]
        if (isset($value[0]) && is_array($value[0]) && array_key_exists('value', $value[0])) {
            return (string) $value[0]['value'];
        }
        return $value;
    }
}
