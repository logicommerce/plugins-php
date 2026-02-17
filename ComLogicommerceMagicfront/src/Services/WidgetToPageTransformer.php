<?php

namespace Plugins\ComLogicommerceMagicfront\Services;

use SDK\Core\Dtos\ElementCollection;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetInstance;

/**
 * Transforms WidgetInstance DTOs from DCS API to Page format expected by PageRelationResolver.
 *
 * The DCS API returns widgets with:
 * - id (string): widget ID
 * - type: widget type (e.g., "banner", "heading")
 * - orderIndex: visual order
 * - propertyValues: widget properties
 * - children: nested child widgets
 *
 * PageRelationResolver expects Page objects with:
 * - id (int): internal ID (we use 0 and store string ID in draftId)
 * - customType: widget type
 * - position: visual order
 * - customTagValues: properties as tags
 * - subpages: nested children
 *
 * @package Plugins\ComLogicommerceMagicfront\Services
 */
class WidgetToPageTransformer {

    /**
     * Transforms a single widget array (from API response) to Page format.
     * Public method for use in controllers that fetch individual widgets.
     *
     * @param array $widgetData Raw widget data from DCS API
     * @return Page
     */
    public static function transformSingle(array $widgetData): Page {
        $widget = new WidgetInstance($widgetData);
        return self::widgetToPage($widget);
    }

    /**
     * Transforms ElementCollection of WidgetInstance to ElementCollection of Page.
     */
    public static function transform(?ElementCollection $widgets): ?ElementCollection {
        if ($widgets === null) {
            return null;
        }

        $items = $widgets->getItems();
        if ($items === null || empty($items)) {
            return new ElementCollection(['items' => []]);
        }

        $pages = [];
        foreach ($items as $widget) {
            if ($widget instanceof WidgetInstance) {
                $pages[] = self::widgetToPage($widget);
            } elseif (is_array($widget)) {
                // Handle raw array data
                $widgetInstance = new WidgetInstance($widget);
                $pages[] = self::widgetToPage($widgetInstance);
            }
        }

        return new ElementCollection(['items' => $pages]);
    }

    /**
     * Converts a single WidgetInstance to Page format.
     */
    private static function widgetToPage(WidgetInstance $widget): Page {
        // First transform children to arrays (SDK expects arrays, not Page objects)
        $childrenArrays = self::transformChildrenToArrays($widget->getChildren());

        // Extract language and moduleSettings from propertyValues
        $extracted = self::extractPropertiesForTemplate(
            $widget->getType(),
            $widget->getPropertyValues(),
            count($widget->getChildren())  // Pass children count for smart defaults
        );

        $pageData = [
            'id' => 0,  // SDK uses int, we store string ID in draftId
            'customType' => $widget->getType(),
            'position' => $widget->getOrderIndex(),
            'pageType' => 'CUSTOM',  // Widgets are always CUSTOM type
            'active' => true,
            'customTagValues' => self::propertyValuesToCustomTags($widget->getPropertyValues()),
            'subpages' => $childrenArrays,
            'language' => $extracted['language'],
            'moduleSettings' => $extracted['moduleSettings'],
        ];

        $page = new Page($pageData);
        $page->setDraftId($widget->getId());  // Store DCS ID as draftId

        return $page;
    }

    /**
     * Extract ALL properties into moduleSettings by pId.
     * Templates access via: moduleSettings['heading.title'], moduleSettings['columns.count'], etc.
     *
     * @param string $type Widget type
     * @param array $propertyValues Properties from API
     * @param int $childrenCount Number of children (for smart defaults)
     */
    private static function extractPropertiesForTemplate(string $type, array $propertyValues, int $childrenCount = 0): array {
        $moduleSettings = [];

        foreach ($propertyValues as $pv) {
            $propId = is_array($pv)
                ? ($pv['propertyId'] ?? '')
                : ($pv->propertyId ?? '');

            $value = is_array($pv) ? ($pv['value'] ?? '') : ($pv->value ?? '');

            if ($propId === '') {
                continue;
            }

            // v2: propId simplificado (title). Guardar también versión prefijada (heading.title) para no romper Twig viejo.
            $moduleSettings[$propId] = $value;
            if ($type !== '' && strpos($propId, '.') === false && !preg_match('/^[A-Z_]+\./', $propId)) {
                $moduleSettings[$type . '.' . $propId] = $value;
            }
        }

        // Smart defaults
        if ($type === 'columns' && $childrenCount > 0) {
            // v2: "count" sin prefijo
            $moduleSettings['count'] = $moduleSettings['count'] ?? $childrenCount;
            // compat con Twig viejo
            $moduleSettings['columns.count'] = $moduleSettings['columns.count'] ?? $childrenCount;
        }

        return [
            'moduleSettings' => $moduleSettings,
            'language' => [],
        ];
    }


    /**
     * Converts propertyValues array to customTagValues format.
     */
    private static function propertyValuesToCustomTags(array $propertyValues): array {
        $customTags = [];
        foreach ($propertyValues as $pv) {
            if (is_array($pv)) {
                $id = $pv['propertyId'] ?? '';
                $customTags[] = [
                    'customTagPId' => $id,
                    'value' => $pv['value'] ?? '',
                ];
            } elseif (is_object($pv)) {
                $id = $pv->propertyId ?? '';
                $customTags[] = [
                    'customTagPId' => $id,
                    'value' => $pv->value ?? '',
                ];
            }
        }
        return $customTags;
    }

    /**
     * Transforms children array recursively to array format (for SDK setSubpages).
     * SDK expects arrays that it converts to Page objects via PageFactory.
     */
    private static function transformChildrenToArrays(array $children): array {
        $subpages = [];
        foreach ($children as $child) {
            $childData = is_array($child) ? $child : [];
            if ($child instanceof WidgetInstance) {
                $childData = [
                    'id' => $child->getId(),
                    'type' => $child->getType(),
                    'orderIndex' => $child->getOrderIndex(),
                    'propertyValues' => $child->getPropertyValues(),
                    'children' => $child->getChildren(),
                ];
            }

            $childType = $childData['type'] ?? '';
            $childProps = $childData['propertyValues'] ?? [];
            $nestedChildren = $childData['children'] ?? [];

            // Extract language and moduleSettings for templates
            $extracted = self::extractPropertiesForTemplate($childType, $childProps);

            // Build page-compatible array
            // NOTE: SDK Page class uses 'pId' field, not 'draftId'
            // The Twig macro needs to use page.pId as fallback
            $subpages[] = [
                'id' => 0,
                'pId' => $childData['id'] ?? '',
                'customType' => $childType,
                'position' => $childData['orderIndex'] ?? 0,
                'pageType' => 'CUSTOM',
                'active' => true,
                'customTagValues' => self::propertyValuesToCustomTags($childProps),
                'subpages' => self::transformChildrenToArrays($nestedChildren),
                'language' => $extracted['language'],
                'moduleSettings' => $extracted['moduleSettings'],
            ];
        }
        return $subpages;
    }
}
