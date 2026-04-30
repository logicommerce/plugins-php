<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;

/**
 * Collects unique widget template type identifiers from either a tree of
 * plugin Pages (renderer side) or a flat list of WidgetInstance DTOs
 * (customize-handler side).
 *
 * Child-structure UUIDs (auto-generated ids for per-item loops) are
 * excluded by default because they do not resolve to standalone templates.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class WidgetTypeCollector {

    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Recursively collect unique widget types from a tree of plugin Pages.
     *
     * @param  array $pages                  Plugin Page objects (or anything exposing getCustomType / getSubpages).
     * @param  bool  $excludeChildStructure  Exclude UUID-shaped types (default true).
     * @return string[]                      Distinct widget types in first-seen order.
     */
    public static function fromPages(array $pages, bool $excludeChildStructure = true): array {
        $types = [];
        self::walkPages($pages, $types, $excludeChildStructure);
        return array_values(array_unique($types));
    }

    /**
     * Collect unique widget types from a flat list of WidgetInstance DTOs.
     *
     * @param  WidgetInstance[] $widgets
     * @param  bool             $excludeChildStructure Exclude UUID-shaped types (default true).
     * @return string[]
     */
    public static function fromWidgets(array $widgets, bool $excludeChildStructure = true): array {
        $types = [];
        foreach ($widgets as $widget) {
            if (!$widget instanceof WidgetInstance) {
                continue;
            }
            $type = $widget->getType();
            if ($type === '' || ($excludeChildStructure && self::isChildStructureUuid($type))) {
                continue;
            }
            $types[$type] = true;
        }
        return array_keys($types);
    }

    /**
     * True when `$type` matches the child-structure UUID shape emitted by
     * the API when auto-generating per-item ids.
     */
    public static function isChildStructureUuid(string $type): bool {
        return (bool) preg_match(self::UUID_PATTERN, $type);
    }

    private static function walkPages(array $pages, array &$types, bool $excludeChildStructure): void {
        foreach ($pages as $page) {
            if (!method_exists($page, 'getCustomType')) {
                continue;
            }
            $type = $page->getCustomType();
            if ($type !== '' && (!$excludeChildStructure || !self::isChildStructureUuid($type))) {
                $types[] = $type;
            }
            $subpages = method_exists($page, 'getSubpages') ? ($page->getSubpages() ?? []) : [];
            if (!empty($subpages)) {
                self::walkPages($subpages, $types, $excludeChildStructure);
            }
        }
    }
}
