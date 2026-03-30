<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Dtos\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Core\Services\StyleMapper;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;

class CustomizeCssHandler extends AbstractCustomizeHandler {

    private const CSS_PROPERTIES = [
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'border-width',
        'border-style',
        'border-color',
        'border-radius',
        'border-top-width',
        'border-right-width',
        'border-bottom-width',
        'border-left-width',
        'border-top-color',
        'border-right-color',
        'border-bottom-color',
        'border-left-color',
        'color',
        'font-family',
        'font-size',
        'font-weight',
        'font-style',
        'line-height',
        'letter-spacing',
        'text-align',
        'text-decoration',
        'text-transform',
        'width',
        'height',
        'min-width',
        'min-height',
        'max-width',
        'max-height',
        'display',
        'position',
        'top',
        'right',
        'bottom',
        'left',
        'z-index',
        'background-color',
        'background-image',
        'background-size',
        'background-position',
        'background-repeat',
        'flex-direction',
        'flex-wrap',
        'justify-content',
        'align-items',
        'align-content',
        'gap',
        'row-gap',
        'column-gap',
        'opacity',
        'overflow',
        'cursor',
        'box-shadow',
        'text-shadow',
    ];

    public function supports(string $type): bool {
        return $type === FunctionType::CUSTOMIZE_CSS;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        try {
            $token = $controller->getRequestParamValue(Parameters::TOKEN, false);

            if (empty($token)) {
                return $this->getFallbackCss();
            }

            if (!$this->isValidToken($token)) {
                return $this->getFallbackCss();
            }

            $pageId = $controller->getRequestParamValue(Parameters::PAGE, false);
            $language = $controller->getRequestParamValue(Parameters::LANGUAGE, false) ?? 'en';

            // Get widgets service
            $widgetsService = WidgetsService::getInstance();

            $widgets = [];
            $widgetTypes = [];
            $filterByPage = !empty($pageId) && $this->isValidPageId($pageId);
            if ($filterByPage) {
                $widgets = $this->getPageWidgets($token, $pageId, $language);
                $widgetTypes = $this->collectWidgetTypes($widgets);
            }

            // Get widget templates - CSS + style schema for mapping elementId
            $templates = $widgetsService->getWidgetTemplates($token, 'templateCss,applicableStyles,childStructure');

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
        return "/* Magic front Custom CSS: No widgets or failed to load */\n";
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
     * Build CSS selector for a widget (or its sub-element)
     */
    private function buildSelector(string $widgetId, string $elementId): string {
        // Sanitize widgetId
        $escapedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $widgetId);

        // Base selector using widget wrapper
        $baseSelector = ".mff-widget[data-widget-id=\"{$escapedId}\"]";

        if ($elementId === '') {
            return $baseSelector;
        }

        // Target element via data-mff-el attribute (unique per widget instance, no duplicate IDs)
        $escapedElementId = preg_replace('/[^a-zA-Z0-9_-]/', '', $elementId);
        return "{$baseSelector} [data-mff-el=\"{$escapedElementId}\"]";
    }
}
