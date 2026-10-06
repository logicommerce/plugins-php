<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

/**
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
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
        // A page id, or a Studio document ref: `store:<slug>` / `store:<slug>@N` / `draft:<id>`.
        // The backend serves those refs only to a preview token minted for exactly that document.
        return $pageId !== '' && (bool) preg_match('/^(?:[a-zA-Z0-9_-]+|store:[a-z0-9-]+(?:@\d+)?|draft:[a-zA-Z0-9_-]+)$/', $pageId);
    }

    /**
     * Get all widgets for a page as a flattened list.
     *
     * @return WidgetInstance[]
     */
    protected function getPageWidgets(string $pageId, string $language): array {
        $items = WidgetsService::getInstance()->getPageWidgetInstances($pageId, $language);
        return empty($items) ? [] : WidgetTypeCollector::flatten($items);
    }
}
