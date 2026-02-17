<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\StyleMapper;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;

class CustomizeCssHandler extends AbstractPluginRouteHandler {

    private const CSS_PROPERTIES = [
        'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
        'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
        'border-width', 'border-style', 'border-color', 'border-radius',
        'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
        'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
        'color', 'font-family', 'font-size', 'font-weight', 'font-style',
        'line-height', 'letter-spacing', 'text-align', 'text-decoration', 'text-transform',
        'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
        'display', 'position', 'top', 'right', 'bottom', 'left', 'z-index',
        'background-color', 'background-image', 'background-size', 'background-position', 'background-repeat',
        'flex-direction', 'flex-wrap', 'justify-content', 'align-items', 'align-content',
        'gap', 'row-gap', 'column-gap',
        'opacity', 'overflow', 'cursor', 'box-shadow', 'text-shadow',
    ];

    public function supports(string $type): bool {
        return $type === FunctionType::CUSTOMIZE_CSS;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        try {
            // Get required parameters from request
            $dcsToken = $controller->getRequestParamValue(Parameters::DCS_TOKEN, false);

            // If not found, try POST directly
            if (empty($dcsToken) && isset($_POST['dcsToken'])) {
                $dcsToken = $_POST['dcsToken'];
            }

            // If no token, return fallback CSS
            if (empty($dcsToken)) {
                return $this->getFallbackCss();
            }

            // SECURITY: Validate token
            if (!$this->isValidToken($dcsToken)) {
                return $this->getFallbackCss();
            }

            $pageId = $controller->getRequestParamValue(Parameters::DCS_PAGE_ID, false);
            if (empty($pageId) && isset($_POST['dcsPageId'])) {
                $pageId = $_POST['dcsPageId'];
            }
            if (empty($pageId) && isset($_POST['pageId'])) {
                $pageId = $_POST['pageId'];
            }

            $language = $controller->getRequestParamValue('language', false);
            if (empty($language) && isset($_POST['language'])) {
                $language = $_POST['language'];
            }
            if (empty($language)) {
                $language = 'en';
            }

            // Get widgets service
            $widgetsService = WidgetsService::getInstance();

            $widgets = [];
            $widgetTypes = [];
            $filterByPage = !empty($pageId) && $this->isValidPageId($pageId);
            if ($filterByPage) {
                $widgets = $this->getPageWidgets($dcsToken, $pageId, $language);
                $widgetTypes = $this->collectWidgetTypes($widgets);
            }

            // Get widget templates - CSS + style schema for mapping elementId
            $templates = $widgetsService->getWidgetTemplates($dcsToken, 'templateCss,applicableStyles,childStructure');

            if (empty($templates) || !is_array($templates)) {
                $templates = [];
            }

            if ($filterByPage) {
                if (!empty($widgetTypes)) {
                    $templates = $this->filterTemplatesByTypes($templates, $widgetTypes);
                } else {
                    $templates = [];
                }
            }

            $styleElementMap = $this->buildStyleElementMap($templates);

            // Aggregate CSS from all templates
            $cssArray = [];
            foreach ($templates as $type => $template) {
                if (is_array($template) && isset($template['templateCss'])) {
                    $css = $template['templateCss'];
                    if (is_string($css) && !empty(trim($css))) {
                        $cssArray[$type] = trim($css);
                    }
                } elseif (is_string($template) && !empty(trim($template))) {
                    // Legacy: if template is string (old format)
                    $cssArray[$type] = trim($template);
                }
            }

            // Merge CSS
            $mergedCss = $this->mergeCss($cssArray);

            $instanceCss = $this->generateInstanceCss($widgets, $styleElementMap);

            // Combine template CSS + instance CSS
            $finalCss = $mergedCss;
            if (!empty($instanceCss)) {
                if (!empty($finalCss)) {
                    $finalCss .= "\n\n";
                }
                $finalCss .= "/* Widget Instance Styles */\n" . $instanceCss;
            }

            // Return CSS or fallback if empty
            return !empty(trim($finalCss)) ? $finalCss : $this->getFallbackCss();
        } catch (\Throwable $e) {
            // Log error
            $logFile = '/home/qinglun/logicommerce/local/phpProject/logs/dcs-error.log';
            $msg = date('Y-m-d H:i:s') . " CSS Handler ERROR: " . $e->getMessage() . "\n";
            $msg .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
            @file_put_contents($logFile, $msg, FILE_APPEND);

            // Return fallback CSS on error
            return $this->getFallbackCss();
        }
    }

    /**
     * Merge multiple CSS strings into one
     */
    private function mergeCss(array $cssArray): string {
        if (empty($cssArray)) {
            return '';
        }

        $merged = [];
        foreach ($cssArray as $type => $css) {
            if (!empty($css)) {
                $sanitizedType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
                $merged[] = "/* Widget Template: {$sanitizedType} */";
                $merged[] = $css;
                $merged[] = "";
            }
        }

        return implode("\n", $merged);
    }

    public function getRawResponseContentType(): ?string {
        return 'text/css; charset=' . \CHARSET;
    }

    /**
     * Fallback CSS when API fails or parameters are missing
     */
    private function getFallbackCss(): string {
        return "/* DCS Custom CSS: No widgets or failed to load */\n";
    }

    /**
     * Validate pageId format
     * Expected format: p-{uuid} (e.g., p-ac3e1133-7da5-443c-a146-415cf62933f1)
     *
     * @param string $pageId Page ID to validate
     * @return bool True if valid format
     */
    private function isValidPageId(string $pageId): bool {
        return !empty($pageId) && preg_match('/^[a-zA-Z0-9_-]+$/', $pageId);
    }

    /**
     * Get all widgets for a page (flattened tree)
     */
    private function getPageWidgets(string $dcsToken, string $pageId, string $language): array {
        try {
            $widgetsService = WidgetsService::getInstance();
            $items = $widgetsService->getPageWidgetInstances($pageId, $language, $dcsToken);

            if (empty($items)) {
                return [];
            }

            return $this->flattenWidgetTree($items);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Flatten widget tree (recursive)
     */
    private function flattenWidgetTree(array $widgets): array {
        $flat = [];

        foreach ($widgets as $widget) {
            if (is_array($widget)) {
                $widget = new WidgetInstance($widget);
            }

            if (!$widget instanceof WidgetInstance) {
                continue;
            }

            $flat[] = $widget;

            $children = $widget->getChildren();
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenWidgetTree($children));
            }
        }

        return $flat;
    }

    /**
     * Generate CSS for all widget instances
     */
    private function generateInstanceCss(array $widgets, array $styleElementMap): string {
        if (empty($widgets)) {
            return '';
        }

        $cssBlocks = [];

        foreach ($widgets as $widget) {
            if (!$widget instanceof WidgetInstance) {
                continue;
            }

            $widgetId = $widget->getId();
            $type = $widget->getType();
            $typeMap = $styleElementMap[$type] ?? [];

            $styleValues = $widget->getStyleValues();
            if (empty($styleValues)) {
                $styleValues = $this->propertyValuesToStyleValues($widget->getPropertyValues(), $typeMap);
            } else {
                $styleValues = $this->applyElementIdMapping($styleValues, $typeMap);
            }

            if (empty($styleValues)) {
                continue;
            }

            // Group styleValues by element key
            $byElement = [];
            foreach ($styleValues as $style) {
                $elementId = $style['elementId']
                    ?? $style['htmlKey']
                    ?? $style['elementKey']
                    ?? $style['element']
                    ?? '';

                if ($elementId === '') {
                    continue;
                }
                $byElement[$elementId][] = $style;
            }

            // Generate CSS for each element
            foreach ($byElement as $elementId => $styles) {
                $selector = $this->buildSelector($widgetId, $elementId);
                $declarations = StyleMapper::generateCssDeclarations($styles);

                if (empty($declarations)) {
                    continue;
                }

                $cssBlock = $selector . " {\n";
                foreach ($declarations as $prop => $value) {
                    $cssBlock .= "  {$prop}: {$value};\n";
                }
                $cssBlock .= "}\n";

                $cssBlocks[] = $cssBlock;
            }
        }

        return implode("\n", $cssBlocks);
    }

    /**
     * Convert propertyValues into styleValues format (fallback)
     */
    private function propertyValuesToStyleValues(array $propertyValues, array $typeMap): array {
        $styleValues = [];

        foreach ($propertyValues as $pv) {
            $propId = is_array($pv)
                ? ($pv['propertyId'] ?? '')
                : ($pv->propertyId ?? '');

            if ($propId === '' || !$this->isCssPropertyId($propId)) {
                continue;
            }

            $enabled = is_array($pv)
                ? ($pv['enabled'] ?? true)
                : ($pv->enabled ?? true);

            if ($enabled === false) {
                continue;
            }

            $value = is_array($pv)
                ? ($pv['value'] ?? '')
                : ($pv->value ?? '');

            if ($value === '') {
                continue;
            }

            $elementId = is_array($pv)
                ? ($pv['elementId'] ?? $pv['htmlKey'] ?? $pv['elementKey'] ?? $pv['element'] ?? '')
                : ($pv->elementId ?? $pv->htmlKey ?? $pv->elementKey ?? $pv->element ?? '');

            if ($elementId === '' && !empty($typeMap[$propId])) {
                $elementId = $typeMap[$propId];
            }

            if ($elementId === '') {
                continue;
            }

            $styleValues[] = [
                'styleTagPId' => $propId,
                'value' => $value,
                'elementId' => $elementId,
            ];
        }

        return $styleValues;
    }

    /**
     * Check if propertyId looks like a CSS property name
     */
    private function isCssPropertyId(string $propertyId): bool {
        $lower = strtolower($propertyId);
        return strpos($lower, '--') === 0
            || in_array($lower, self::CSS_PROPERTIES, true)
            || stripos($propertyId, 'SPACING.') === 0
            || stripos($propertyId, 'BORDER.') === 0
            || stripos($propertyId, 'SHADOW.') === 0;
    }

    /**
     * Apply elementId mapping from template schema when missing
     */
    private function applyElementIdMapping(array $styleValues, array $typeMap): array {
        if (empty($styleValues) || empty($typeMap)) {
            return $styleValues;
        }

        foreach ($styleValues as $i => $style) {
            if (!is_array($style)) {
                continue;
            }

            $elementId = $style['elementId']
                ?? $style['htmlKey']
                ?? $style['elementKey']
                ?? $style['element']
                ?? '';

            if ($elementId !== '') {
                continue;
            }

            $propId = $style['styleTagPId'] ?? $style['propertyId'] ?? '';
            if ($propId !== '' && !empty($typeMap[$propId])) {
                $style['elementId'] = $typeMap[$propId];
                $styleValues[$i] = $style;
            }
        }

        return $styleValues;
    }

    /**
     * Build propertyId -> elementId map per widget type from template schema
     */
    private function buildStyleElementMap(array $templates): array {
        $map = [];

        foreach ($templates as $type => $template) {
            if (!is_array($template)) {
                continue;
            }

            $styleMap = [];
            $styles = [];

            if (isset($template['applicableStyles']) && is_array($template['applicableStyles'])) {
                $styles = array_merge($styles, $template['applicableStyles']);
            }

            if (
                isset($template['childStructure']) &&
                is_array($template['childStructure']) &&
                isset($template['childStructure']['applicableStyles']) &&
                is_array($template['childStructure']['applicableStyles'])
            ) {
                $styles = array_merge($styles, $template['childStructure']['applicableStyles']);
            }

            foreach ($styles as $style) {
                if (!is_array($style)) {
                    continue;
                }
                $propId = $style['propertyId'] ?? '';
                $elementId = $style['elementId'] ?? '';
                if ($propId !== '' && $elementId !== '') {
                    $styleMap[$propId] = $elementId;
                }
            }

            if (!empty($styleMap)) {
                $map[$type] = $styleMap;
            }
        }

        return $map;
    }

    /**
     * Collect widget types from flattened widget list
     */
    private function collectWidgetTypes(array $widgets): array {
        $types = [];

        foreach ($widgets as $widget) {
            if ($widget instanceof WidgetInstance) {
                $type = $widget->getType();
                if (is_string($type) && $type !== '') {
                    $types[] = $type;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * Filter templates by widget types
     */
    private function filterTemplatesByTypes(array $templates, array $types): array {
        if (empty($templates) || empty($types)) {
            return $templates;
        }

        $typeLookup = array_fill_keys($types, true);
        return array_intersect_key($templates, $typeLookup);
    }

    /**
     * Build CSS selector for a widget (or its sub-element)
     */
    private function buildSelector(string $widgetId, string $elementId): string {
        // Sanitize widgetId
        $escapedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $widgetId);

        // Base selector using widget wrapper
        $baseSelector = ".dcs-widget[data-widget-id=\"{$escapedId}\"]";

        // If root element, return base selector
        if ($elementId === 'root' || $elementId === '') {
            return $baseSelector;
        }

        // Nested element: use id from template schema
        $escapedElementId = preg_replace('/[^a-zA-Z0-9_-]/', '', $elementId);
        return "{$baseSelector} #{$escapedElementId}";
    }

    /**
     * Validate JWT token format
     *
     * @param string $token Token to validate
     * @return bool True if valid JWT format
     */
    private function isValidToken(string $token): bool {
        // JWT format: header.payload.signature (each part is base64url encoded)
        // Example: eyJhbGc...eyJzdWI...uoDkfM1v...
        $parts = explode('.', $token);

        // Must have exactly 3 parts
        if (count($parts) !== 3) {
            return false;
        }

        // Each part should be valid base64url (alphanumeric, -, _)
        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                return false;
            }
        }

        return true;
    }
}
