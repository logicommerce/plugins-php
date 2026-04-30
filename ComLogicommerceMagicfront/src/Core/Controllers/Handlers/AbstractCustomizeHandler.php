<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

abstract class AbstractCustomizeHandler extends AbstractPluginRouteHandler {

    /**
     * Validate JWT token format (header.payload.signature, each part base64url).
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

    protected function isValidPageId(string $pageId): bool {
        return $pageId !== '' && (bool) preg_match('/^[a-zA-Z0-9_-]+$/', $pageId);
    }

    /**
     * Get all widgets for a page as a flattened list.
     *
     * @return WidgetInstance[]
     */
    protected function getPageWidgets(string $token, string $pageId, string $language): array {
        $items = WidgetsService::getInstance()
            ->setToken($token)
            ->getPageWidgetInstances($pageId, $language);

        return empty($items) ? [] : $this->flattenWidgetTree($items);
    }

    /**
     * Flatten widget tree recursively. Children are guaranteed to be WidgetInstance[]
     * thanks to WidgetInstance::setChildren() hydrating them on construction.
     *
     * @param  WidgetInstance[] $widgets
     * @return WidgetInstance[]
     */
    protected function flattenWidgetTree(array $widgets): array {
        $flat = [];
        foreach ($widgets as $widget) {
            $flat[] = $widget;
            $children = $widget->getChildren();
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenWidgetTree($children));
            }
        }
        return $flat;
    }

    /**
     * Collect unique widget types from a flattened widget list.
     *
     * @param  WidgetInstance[] $widgets
     * @return string[]
     */
    protected function collectWidgetTypes(array $widgets): array {
        return WidgetTypeCollector::fromWidgets($widgets);
    }
}
