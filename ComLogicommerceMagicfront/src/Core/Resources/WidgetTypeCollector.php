<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;

/**
 * Collects unique widget template type identifiers from either a tree of
 * plugin Pages (renderer side) or a flat list of WidgetInstance DTOs
 * (customize-handler side).
 *
 * `childStructure` pseudo-widgets are always skipped: they have no standalone
 * template, CSS/JS belong to the parent. The classification lives on the DTO
 * itself ({@see WidgetInstance::isChildStructurePseudo()} and the plugin
 * Page's equivalent in MagicfrontPageTrait); this collector just asks.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class WidgetTypeCollector {

    /**
     * Recursively collect unique widget types from a tree of plugin Pages.
     *
     * @param  Page[]   $pages
     * @return string[] Distinct widget types in first-seen order.
     */
    public static function fromPages(array $pages): array {
        $types = [];
        self::walkPages($pages, $types);
        return array_values(array_unique($types));
    }

    /**
     * Flatten a recursive WidgetInstance tree into a single linear list.
     * Non-WidgetInstance entries are silently dropped so callers can hand over
     * an already-mixed array straight from JSON deserialisation.
     *
     * @param  WidgetInstance[] $widgets Roots (or nested roots) of the tree.
     * @return WidgetInstance[]
     */
    public static function flatten(array $widgets): array {
        $flat = [];
        foreach ($widgets as $widget) {
            if (!$widget instanceof WidgetInstance) {
                continue;
            }
            $flat[] = $widget;
            $children = $widget->getChildren();
            if (!empty($children)) {
                $flat = array_merge($flat, self::flatten($children));
            }
        }
        return $flat;
    }

    /**
     * Collect unique widget types from a flat list of WidgetInstance DTOs.
     *
     * @param  WidgetInstance[] $widgets
     * @return string[]
     */
    public static function fromWidgets(array $widgets): array {
        $types = [];
        foreach ($widgets as $widget) {
            if (!$widget instanceof WidgetInstance) {
                continue;
            }
            $type = $widget->getType();
            if ($type === '' || $widget->isChildStructurePseudo()) {
                continue;
            }
            $types[$type] = true;
        }
        return array_keys($types);
    }

    /**
     * @param Page[]   $pages
     * @param string[] $types
     */
    private static function walkPages(array $pages, array &$types): void {
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $type = $page->getCustomType();
            if ($type !== '' && !$page->isChildStructurePseudo()) {
                $types[] = $type;
            }
            $subpages = $page->getSubpages() ?? [];
            if (!empty($subpages)) {
                self::walkPages($subpages, $types);
            }
        }
    }
}
