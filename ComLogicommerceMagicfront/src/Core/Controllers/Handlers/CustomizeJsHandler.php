<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;

class CustomizeJsHandler extends AbstractCustomizeHandler {

    public function supports(string $type): bool {
        return $type === FunctionType::CUSTOMIZE_JS;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        try {
            $token = $controller->getRequestParamValue(Parameters::TOKEN, false);

            if (empty($token)) {
                return $this->getFallbackJs();
            }

            if (!$this->isValidToken($token)) {
                return $this->getFallbackJs();
            }

            $pageId = $controller->getRequestParamValue(Parameters::PAGE, false);
            $language = $controller->getRequestParamValue(Parameters::LANGUAGE, false) ?? 'en';

            // Get widgets service
            $widgetsService = WidgetsService::getInstance();

            $widgetTypes = [];
            $filterByPage = !empty($pageId) && $this->isValidPageId($pageId);
            if ($filterByPage) {
                $widgets = $this->getPageWidgets($token, $pageId, $language);
                $widgetTypes = $this->collectWidgetTypes($widgets);
            }

            // Get widget templates - only JS needed
            $templates = $widgetsService->getWidgetTemplates($token, 'templateJs');

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

            // Aggregate JavaScript from all templates
            $jsArray = [];
            foreach ($templates as $type => $template) {
                if (is_array($template) && isset($template['templateJs'])) {
                    $js = $template['templateJs'];
                    if (is_string($js) && !empty(trim($js))) {
                        $jsArray[$type] = trim($js);
                    }
                }
            }

            // Merge JavaScript
            $mergedJs = $this->mergeJs($jsArray);

            // Return JavaScript or fallback if empty
            return !empty($mergedJs) ? $mergedJs : $this->getFallbackJs();
        } catch (\Throwable $e) {
            // Return fallback JavaScript on error
            return $this->getFallbackJs();
        }
    }

    /**
     * Merge multiple JavaScript strings into one
     */
    private function mergeJs(array $jsArray): string {
        if (empty($jsArray)) {
            return '';
        }

        $merged = [];
        foreach ($jsArray as $type => $js) {
            if (!empty($js)) {
                $sanitizedType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
                $merged[] = "// Widget Template: {$sanitizedType}";
                $merged[] = "(function() {";
                $merged[] = "    'use strict';";
                $merged[] = "    " . str_replace("\n", "\n    ", $js);
                $merged[] = "})();";
                $merged[] = "";
            }
        }

        return implode("\n", $merged);
    }

    public function getRawResponseContentType(): ?string {
        return 'application/javascript; charset=' . \CHARSET;
    }

    /**
     * Fallback JavaScript when API fails or parameters are missing
     */
    private function getFallbackJs(): string {
        return "// Magic front Custom JavaScript: No widgets or failed to load\n";
    }
}
