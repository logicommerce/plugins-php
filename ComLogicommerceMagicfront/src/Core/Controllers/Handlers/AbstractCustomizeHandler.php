<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Core\Dtos\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;

abstract class AbstractCustomizeHandler extends AbstractPluginRouteHandler {

    /**
     * Validate JWT token format (header.payload.signature, each part base64url)
     */
    protected function isValidToken(string $token): bool {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return false;
        }

        foreach ($parts as $part) {
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate pageId format: alphanumeric, dashes and underscores allowed
     */
    protected function isValidPageId(string $pageId): bool {
        return !empty($pageId) && preg_match('/^[a-zA-Z0-9_-]+$/', $pageId);
    }

    /**
     * Get all widgets for a page as a flattened list
     */
    protected function getPageWidgets(string $token, string $pageId, string $language): array {
        try {
            $items = WidgetsService::getInstance()->getPageWidgetInstances($pageId, $language, $token);

            if (empty($items)) {
                return [];
            }

            return $this->flattenWidgetTree($items);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Flatten widget tree recursively
     */
    protected function flattenWidgetTree(array $widgets): array {
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
     * Collect unique widget types from a flattened widget list
     */
    protected function collectWidgetTypes(array $widgets): array {
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
     * Filter templates keeping only those whose key matches the given types
     */
    protected function filterTemplatesByTypes(array $templates, array $types): array {
        if (empty($templates) || empty($types)) {
            return $templates;
        }

        $typeLookup = array_fill_keys($types, true);
        return array_intersect_key($templates, $typeLookup);
    }
}
