<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers;

use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;

class CustomizeJsHandler extends AbstractPluginRouteHandler {

    public function supports(string $type): bool {
        return $type === FunctionType::CUSTOMIZE_JS;
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

            // If no token, return fallback JS
            if (empty($dcsToken)) {
                return $this->getFallbackJs();
            }

            // SECURITY: Validate token
            if (!$this->isValidToken($dcsToken)) {
                return $this->getFallbackJs();
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

            $widgetTypes = [];
            $filterByPage = !empty($pageId) && $this->isValidPageId($pageId);
            if ($filterByPage) {
                $widgets = $this->getPageWidgets($dcsToken, $pageId, $language);
                $widgetTypes = $this->collectWidgetTypes($widgets);
            }

            // Get widget templates - only JS needed
            $templates = $widgetsService->getWidgetTemplates($dcsToken, 'templateJs');

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
            // Log error
            $logFile = '/home/qinglun/logicommerce/local/phpProject/logs/dcs-error.log';
            $msg = date('Y-m-d H:i:s') . " JS Handler ERROR: " . $e->getMessage() . "\n";
            $msg .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
            @file_put_contents($logFile, $msg, FILE_APPEND);

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
        return "// DCS Custom JavaScript: No widgets or failed to load\n";
    }

    /**
     * Validate pageId format
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
     * Validate JWT token format
     *
     * @param string $token Token to validate
     * @return bool True if valid JWT format
     */
    private function isValidToken(string $token): bool {
        // JWT format: header.payload.signature (each part is base64url encoded)
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
