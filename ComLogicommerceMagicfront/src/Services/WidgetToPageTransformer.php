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
        $allPropertyValues = self::mergePropertyValues(
            $widget->getPropertyValues(),
            $widget->getStyleValues()
        );

        // Transform children to arrays with moduleSettings
        $childrenArrays = self::transformChildrenToArrays($widget->getChildren());

        // Extract language and moduleSettings from propertyValues
        $extracted = self::extractPropertiesForTemplate(
            $widget->getType(),
            $allPropertyValues,
            count($widget->getChildren())  // Pass children count for smart defaults
        );

        // Don't pass subpages to constructor — SDK PageFactory would create
        // SDK Page objects that lack moduleSettings (DcsPageTrait property).
        $pageData = [
            'id' => 0,  // SDK uses int, we store string ID in draftId
            'customType' => $widget->getType(),
            'position' => $widget->getOrderIndex(),
            'pageType' => 'CUSTOM',  // Widgets are always CUSTOM type
            'active' => true,
            'customTagValues' => self::propertyValuesToCustomTags($allPropertyValues),
            'subpages' => [],  // Empty — children set below as plugin Pages
            'language' => $extracted['language'],
            'moduleSettings' => $extracted['moduleSettings'],
        ];

        $page = new Page($pageData);
        $page->setDraftId($widget->getId());  // Store DCS ID as draftId

        // Build children as plugin Page objects (with moduleSettings via DcsPageTrait)
        // instead of SDK Page objects created by PageFactory.
        if (!empty($childrenArrays)) {
            $page->setFWKSubpages(self::buildChildPages($childrenArrays));
        }

        return $page;
    }

    /**
     * Recursively builds plugin Page objects from child arrays.
     *
     * SDK's PageFactory creates SDK\Dtos\Catalog\Page\Page objects which don't
     * have the moduleSettings property (from DcsPageTrait). This method creates
     * plugin Page objects so that Twig templates can access subpage.moduleSettings.
     *
     * @param array $childrenArrays Arrays from transformChildrenToArrays()
     * @return Page[]
     */
    private static function buildChildPages(array $childrenArrays): array {
        $pages = [];
        foreach ($childrenArrays as $childArray) {
            // Extract nested subpages before constructing (avoid SDK PageFactory)
            $nestedSubpages = $childArray['subpages'] ?? [];
            $childArray['subpages'] = [];

            // Plugin Page constructor processes moduleSettings via DcsPageTrait
            $childPage = new Page($childArray);
            if (!empty($childArray['pId'])) {
                $childPage->setDraftId($childArray['pId']);
            }

            // Recursively process grandchildren
            if (!empty($nestedSubpages)) {
                $childPage->setFWKSubpages(self::buildChildPages($nestedSubpages));
            }

            $pages[] = $childPage;
        }
        return $pages;
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
            $normalized = self::normalizePropertyEntry($pv);
            if ($normalized['enabled'] === false) {
                continue;
            }

            $propId = $normalized['propertyId'];
            if ($propId === '') {
                continue;
            }

            $value = $normalized['value'];
            $elementId = $normalized['elementId'];

            // v2: propId simplificado (title). Guardar también versión prefijada (heading.title) para no romper Twig viejo.
            $moduleSettings[$propId] = $value;
            if ($elementId !== '') {
                $moduleSettings[$elementId . '.' . $propId] = $value;
            }
            if ($type !== '' && strpos($propId, '.') === false && !preg_match('/^[A-Z_]+\./', $propId)) {
                $moduleSettings[$type . '.' . $propId] = $value;
                if ($elementId !== '') {
                    $moduleSettings[$type . '.' . $elementId . '.' . $propId] = $value;
                }
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
            $normalized = self::normalizePropertyEntry($pv);
            if ($normalized['enabled'] === false || $normalized['propertyId'] === '') {
                continue;
            }

            $customTags[] = [
                'customTagPId' => $normalized['propertyId'],
                'value' => $normalized['value'],
            ];
        }
        return $customTags;
    }

    /**
     * Normalize property value entry across API versions.
     */
    private static function normalizePropertyEntry($propertyValue): array {
        $propId = '';
        $value = '';
        $elementId = '';
        $enabled = true;

        if (is_array($propertyValue)) {
            $propId = $propertyValue['propertyId'] ?? ($propertyValue['pId'] ?? '');
            $value = $propertyValue['value'] ?? '';
            $elementId = $propertyValue['elementId'] ?? '';
            if (array_key_exists('enabled', $propertyValue)) {
                $enabled = (bool)$propertyValue['enabled'];
            }
        } elseif (is_object($propertyValue)) {
            $propId = $propertyValue->propertyId ?? ($propertyValue->pId ?? '');
            $value = $propertyValue->value ?? '';
            $elementId = $propertyValue->elementId ?? '';
            if (property_exists($propertyValue, 'enabled')) {
                $enabled = (bool)$propertyValue->enabled;
            }
        }

        return [
            'propertyId' => $propId,
            'value' => self::normalizeValue($value),
            'elementId' => is_string($elementId) ? $elementId : '',
            'enabled' => $enabled,
        ];
    }

    /**
     * Normalize dimension-like values from the new API.
     */
    private static function normalizeValue($value) {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            $keys = array_keys($value);
            sort($keys);
            if ($keys === ['unit', 'value'] && isset($value['value']) && isset($value['unit'])) {
                $rawValue = $value['value'];
                $unit = $value['unit'];
                if ((is_int($rawValue) || is_float($rawValue) || is_numeric($rawValue)) && is_string($unit)) {
                    if ($unit === '' || $unit === 'px') {
                        return (string)$rawValue;
                    }
                    return (string)$rawValue . $unit;
                }
            }
        }

        return $value;
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
                    'styleValues' => $child->getStyleValues(),
                    'children' => $child->getChildren(),
                ];
            }

            $childType = $childData['type'] ?? '';
            $childProps = $childData['propertyValues'] ?? [];
            $childStyles = $childData['styleValues'] ?? [];
            $allChildValues = self::mergePropertyValues($childProps, $childStyles);
            $nestedChildren = $childData['children'] ?? [];

            // Extract language and moduleSettings for templates
            $extracted = self::extractPropertiesForTemplate($childType, $allChildValues);

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
                'customTagValues' => self::propertyValuesToCustomTags($allChildValues),
                'subpages' => self::transformChildrenToArrays($nestedChildren),
                'language' => $extracted['language'],
                'moduleSettings' => $extracted['moduleSettings'],
            ];
        }
        return $subpages;
    }

    /**
     * Merge standard properties with style values.
     */
    private static function mergePropertyValues(array $propertyValues, array $styleValues): array {
        if (empty($styleValues)) {
            return $propertyValues;
        }

        return array_merge($propertyValues, $styleValues);
    }
}
