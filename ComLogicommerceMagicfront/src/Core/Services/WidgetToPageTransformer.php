<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use SDK\Core\Dtos\ElementCollection;

/**
 * Transforms WidgetInstance DTOs from the Magic Front API into the Page
 * format expected by PageRelationResolver.
 *
 *   Magic Front API structure            →  Page-compatible structure
 *   id (string UUID)                     →  id (int 0) + draftId (string UUID)
 *   widgetTemplateId                     →  customType
 *   orderIndex                           →  position
 *   propertyValues + styleValues         →  customTagValues + moduleSettings
 *   children                             →  subpages (plugin Page objects)
 *
 * Thin wrapper around {@see WidgetArrayTransformer} (the self-contained,
 * SDK-free core also used by the docker template-renderer): convert
 * WidgetInstance → raw array, run the shared array transform, wrap the
 * resulting array tree back into plugin Page DTOs (plugin Page is needed so
 * Twig can read `subpage.moduleSettings` from {@see MagicfrontPageTrait}).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class WidgetToPageTransformer {

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Transform a single WidgetInstance DTO to a Page DTO.
     */
    public static function transformSingle(WidgetInstance $widget): Page {
        $pageArray = WidgetArrayTransformer::transformToArray(self::widgetToArray($widget));
        return self::buildPageFromArray($pageArray);
    }

    /**
     * Transform an ElementCollection of WidgetInstances into an
     * ElementCollection of Pages.
     */
    public static function transform(?ElementCollection $widgets): ?ElementCollection {
        if ($widgets === null) {
            return null;
        }

        $items = $widgets->getItems();
        if (empty($items)) {
            return new ElementCollection(['items' => []]);
        }

        $pages = [];
        foreach ($items as $item) {
            $widget  = $item instanceof WidgetInstance ? $item : new WidgetInstance($item);
            $pages[] = self::transformSingle($widget);
        }
        return new ElementCollection(['items' => $pages]);
    }

    // ─── WidgetInstance → array ───────────────────────────────────────────────

    /**
     * Recursively convert a WidgetInstance DTO into the plain array shape
     * the array transformer expects (mirrors what the Magic Front API would
     * send over JSON).
     */
    private static function widgetToArray(WidgetInstance $widget): array {
        $children = [];
        foreach ($widget->getChildren() as $child) {
            if ($child instanceof WidgetInstance) {
                $children[] = self::widgetToArray($child);
            }
        }
        return [
            'id'               => $widget->getId(),
            'widgetTemplateId' => $widget->getWidgetTemplateId(),
            'orderIndex'       => $widget->getOrderIndex(),
            'propertyValues'   => $widget->getPropertyValues(),
            'styleValues'      => $widget->getStyleValues(),
            'children'         => $children,
            'slotId'           => $widget->getSlotId(),
            'slotPermissions'  => $widget->getSlotPermissions(),
        ];
    }

    // ─── array → Page DTO ─────────────────────────────────────────────────────

    /**
     * Build a plugin Page DTO from the page-shaped array produced by
     * {@see WidgetArrayTransformer::transformToArray}.
     *
     * The SDK Page constructor types `id` as int, so we omit the array's
     * string `id` (and its `draftId` alias) from the constructor and set
     * the UUID via {@see Page::setDraftId} after instantiation.
     */
    private static function buildPageFromArray(array $pageArray): Page {
        $uuid     = $pageArray['draftId'] ?? '';
        $subpages = $pageArray['subpages'] ?? [];

        $page = new Page([
            'id'              => 0,
            'customType'      => $pageArray['customType'] ?? '',
            'position'        => $pageArray['position'] ?? 0,
            'pageType'        => $pageArray['pageType'] ?? 'CUSTOM',
            'active'          => $pageArray['active'] ?? true,
            'customTagValues' => $pageArray['customTagValues'] ?? [],
            'subpages'        => [],
            'language'        => $pageArray['language'] ?? [],
            'moduleSettings'  => $pageArray['moduleSettings'] ?? [],
        ]);

        if ($uuid !== '') {
            $page->setDraftId((string) $uuid);
        }
        if (!empty($pageArray['slotId'])) {
            $page->setSlotId($pageArray['slotId']);
        }
        if (!empty($pageArray['slotPermissions'])) {
            $page->setSlotPermissions($pageArray['slotPermissions']);
        }
        if (!empty($subpages)) {
            $page->setFWKSubpages(array_map([self::class, 'buildPageFromArray'], $subpages));
        }
        return $page;
    }
}
