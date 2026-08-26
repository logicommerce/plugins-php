<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Locates a placed widget inside a page blob's tree. Generic replacement for the per-widget locators:
 * the full-page render ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider})
 * resolves by custom type, the {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler}
 * AJAX endpoint resolves by exact widget id, so both walk the same tree the same way.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class WidgetLocator {

    /** The placed instance whose draft/real id equals $widgetId, or null. */
    public static function findById(?ElementCollection $pages, string $widgetId): ?PluginPage {
        if ($widgetId === '') {
            return null;
        }
        return self::walk($pages, static fn (PluginPage $page): bool => (string) ($page->getDraftId() ?: $page->getId()) === $widgetId);
    }

    /** The first placed instance of the given custom type, or null. */
    public static function findByType(?ElementCollection $pages, string $customType): ?PluginPage {
        return self::walk($pages, static fn (PluginPage $page): bool => $page->getCustomType() === $customType);
    }

    /** @param callable(PluginPage): bool $matches */
    private static function walk(?ElementCollection $pages, callable $matches): ?PluginPage {
        if ($pages === null) {
            return null;
        }
        $stack = $pages->getItems();
        while ($stack !== []) {
            $page = array_shift($stack);
            if (!$page instanceof PluginPage) {
                continue;
            }
            if ($matches($page)) {
                return $page;
            }
            $sub = $page->getSubpages();
            if (!empty($sub)) {
                $stack = array_merge($stack, $sub);
            }
        }
        return null;
    }
}
