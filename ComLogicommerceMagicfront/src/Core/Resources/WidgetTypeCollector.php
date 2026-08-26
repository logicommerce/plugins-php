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
class WidgetTypeCollector {

    /**
     * Recursively collect unique widget types from a tree of plugin Pages.
     *
     * @param  Page[]   $pages
     * @return string[] Distinct widget types in first-seen order.
     */
    public static function fromPages(array $pages): array {
        $types = [];
        self::walkPages($pages, $types, false);
        return array_values(array_unique($types));
    }

    /** Like {@see fromPages()} but keyed by the template lookup wire key (version ‖ family). */
    public static function templateKeysFromPages(array $pages): array {
        $types = [];
        self::walkPages($pages, $types, true);
        return array_values(array_unique($types));
    }

    /** Strip the version suffix from a wire key: `heading@2` → `heading`; a bare family is returned as-is. */
    public static function familyOf(string $wireKey): string {
        $at = strpos($wireKey, '@');
        return $at === false ? $wireKey : substr($wireKey, 0, $at);
    }

    /**
     * Look up a wire-keyed map ({@see WidgetInstance::templateKey()}) by an instance's key, falling
     * back to its family for blobs whose schema is family-keyed. Single point for the version‖family
     * resolution shared by every template / style / slot lookup; returns null when neither key is present.
     */
    public static function resolveByKey(array $map, string $key): mixed {
        return $map[$key] ?? $map[self::familyOf($key)] ?? null;
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
        return self::collectWidgets($widgets, false);
    }

    /** Like {@see fromWidgets()} but keyed by the template lookup wire key (version ‖ family). */
    public static function templateKeysFromWidgets(array $widgets): array {
        return self::collectWidgets($widgets, true);
    }

    /**
     * @param  WidgetInstance[] $widgets
     * @return string[]
     */
    private static function collectWidgets(array $widgets, bool $wireKey): array {
        $types = [];
        foreach ($widgets as $widget) {
            if (!$widget instanceof WidgetInstance || $widget->isChildStructurePseudo()) {
                continue;
            }
            $type = $wireKey ? $widget->templateKey() : $widget->getType();
            if ($type === '') {
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
    private static function walkPages(array $pages, array &$types, bool $wireKey): void {
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $type = $wireKey ? $page->getTemplateKey() : $page->getCustomType();
            if ($type !== '' && !$page->isChildStructurePseudo()) {
                $types[] = $type;
            }
            $subpages = $page->getSubpages() ?? [];
            if (!empty($subpages)) {
                self::walkPages($subpages, $types, $wireKey);
            }
        }
    }
}
