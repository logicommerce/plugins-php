<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Functions;

use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Twig\Environment;

/**
 * Render-time helper behind `mff_widget_slot()`. Three shapes:
 *  - renderSlotContainer(): bare/columns — sortable drop zone.
 *  - renderAsWidget(): parametric/fixed-slot — single widget block.
 *  - renderRepeated(): `mff_widget_slot_for()` — a LIST slot painted once per product (the composed
 *    product card of a listing). Card 0 is the editable one; cards i>0 are marked copies.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig\Functions
 */
class WidgetSlotRenderer {

    /** Placeholder rendered for empty slots in preview mode (docker / AI tooling). */
    private const EMPTY_PREVIEW_HTML = '<div style="padding:16px;border:1px dashed #bbb;color:#999;font:12px/1 monospace;text-align:center;background:#f9f9f9;min-height:80px;display:flex;align-items:center;justify-content:center">slot content</div>';

    /** Index of the card `renderRepeated()` is painting; null outside a repeat. The widgets macro reads it
     *  through `mff_repeat_index()` at ANY depth, because a nested template calls the macro with its own
     *  args and a Twig macro sees nothing of its caller's context. */
    private static ?int $repeatIndex = null;

    public static function repeatIndex(): ?int {
        return self::$repeatIndex;
    }

    /** A copy is every card after the first: no MFF_WIDGET_START comments (the canvas registers only card 0),
     *  no HTML ids, no slot/list/container attributes (docs/audits/2026-09-27-tarjeta-de-producto-compuesta.md). */
    public static function isRepeatCopy(): bool {
        return self::$repeatIndex !== null && self::$repeatIndex > 0;
    }

    /** Look up a subpage by slotId. Works on Page objects and arrays. */
    public static function findSlotById(array $context, string $slotId): mixed {
        $page = $context['page'] ?? null;
        if ($page === null) {
            return null;
        }
        $subpages = self::field($page, 'subpages');
        if (!is_array($subpages)) {
            return null;
        }
        foreach ($subpages as $candidate) {
            if (self::field($candidate, 'slotId') === $slotId) {
                return $candidate;
            }
        }
        return null;
    }

    /** Every occupant of a slot, in tree order — a LIST slot holds several. */
    public static function findSlotOccupants(array $context, string $slotId): array {
        $page = $context['page'] ?? null;
        $subpages = $page === null ? null : self::field($page, 'subpages');
        if (!is_array($subpages)) {
            return [];
        }
        $occupants = [];
        foreach ($subpages as $candidate) {
            if (self::field($candidate, 'slotId') === $slotId) {
                $occupants[] = $candidate;
            }
        }
        return $occupants;
    }

    /**
     * Paints the occupants of `slotId` with `page.product` = `$product` on every level of their subtree — the
     * same fan-out as `attachProductToPage` (docker) and `PageRelationResolver::attachProduct` (store) — so the
     * product-page primitives inside the card read THIS card's product. Array pages (docker) are copied; object
     * pages (store) are set and restored afterwards, since the tree is shared by every card.
     */
    public static function renderRepeated(Environment $env, array $context, string $slotId, mixed $product, int $index): string {
        $occupants = self::findSlotOccupants($context, $slotId);
        if (empty($occupants)) {
            return $index === 0 ? self::renderChildren($env, $context, []) : '';
        }
        $previous = self::$repeatIndex;
        self::$repeatIndex = $index;
        $restore = [];
        try {
            $pages = [];
            foreach ($occupants as $occupant) {
                $pages[] = self::withProduct($occupant, $product, $restore);
            }
            return self::renderViaMacro($env, $context, $pages);
        } finally {
            foreach ($restore as [$object, $previousProduct]) {
                $object->setProduct($previousProduct);
            }
            self::$repeatIndex = $previous;
        }
    }

    private static function withProduct(mixed $page, mixed $product, array &$restore): mixed {
        if (is_array($page)) {
            $page['product'] = $product;
            if (is_array($page['subpages'] ?? null)) {
                foreach ($page['subpages'] as $i => $sub) {
                    $page['subpages'][$i] = self::withProduct($sub, $product, $restore);
                }
            }
            return $page;
        }
        if (is_object($page) && method_exists($page, 'setProduct')) {
            $restore[] = [$page, method_exists($page, 'getProduct') ? $page->getProduct() : null];
            $page->setProduct($product);
            $subpages = self::field($page, 'subpages');
            if (is_array($subpages)) {
                foreach ($subpages as $sub) {
                    self::withProduct($sub, $product, $restore);
                }
            }
        }
        return $page;
    }

    public static function renderSlotContainer(Environment $env, array $context, mixed $subPage): string {
        $draftId  = (string) (self::field($subPage, 'draftId') ?? '');
        $rawId    = self::field($subPage, 'id');
        $widgetId = ($rawId === null || $rawId === 0 || $rawId === '') ? $draftId : (string) $rawId;
        $type     = (string) (self::field($subPage, 'customType') ?? '');
        $idAttr   = $draftId !== '' ? $draftId : $widgetId;

        $subpages = self::field($subPage, 'subpages');
        $children = self::renderChildren($env, $context, is_array($subpages) ? $subpages : []);

        $payload = json_encode([
            'type'       => $type,
            'id'         => $idAttr,
            'draftId'    => $draftId,
            'parentId'   => null,
            'label'      => null,
            'isSlotItem' => true,
        ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (self::isRepeatCopy()) {
            return '<div data-mff-repeat-copy="1"'
                 . ' data-mff-widget-type="' . self::escapeAttr($type) . '"'
                 . ' data-mff-widget-id="' . self::escapeAttr($idAttr) . '"'
                 . '>' . $children . '</div>';
        }

        return '<!-- MFF_WIDGET_START ' . $payload . ' -->'
             . '<div id="' . self::escapeAttr($widgetId) . '"'
             . ' data-mff-widget-root="1" data-mff-sortable-container="1"'
             . ' data-mff-widget-type="' . self::escapeAttr($type) . '"'
             . ' data-mff-widget-id="' . self::escapeAttr($idAttr) . '"'
             . '>' . $children . '</div>'
             . '<!-- MFF_WIDGET_END -->';
    }

    public static function renderAsWidget(Environment $env, array $context, mixed $subPage): string {
        return self::renderViaMacro($env, $context, [$subPage]);
    }

    private static function renderChildren(Environment $env, array $context, array $subpages): string {
        if (!empty($subpages)) {
            return self::renderViaMacro($env, $context, $subpages);
        }
        // Empty slot affordance: preview mode only (docker / AI tooling).
        return !empty($context[MagicfrontControllerData::CONTEXT_PREVIEW_MODE]) ? self::EMPTY_PREVIEW_HTML : '';
    }

    private static function renderViaMacro(Environment $env, array $context, array $pages): string {
        return $env->createTemplate(
            "{% import 'macros/widget.twig' as __mffSlotMacros %}"
            . '{{ __mffSlotMacros.widgets({pages: pages, version: version, widgetTemplateList: widgetTemplateList, shared: shared, permission: permission}) }}'
        )->render([
            'pages'              => $pages,
            'version'            => $context['version'] ?? '',
            MagicfrontControllerData::WIDGET_TEMPLATE_LIST => $context[MagicfrontControllerData::WIDGET_TEMPLATE_LIST] ?? [],
            'shared'             => $context['shared'] ?? [],
            'permission'         => $context['permission'] ?? null,
        ]);
    }

    /** Read a field from array or object — mirrors Twig `{{ obj.field }}`. */
    private static function field(mixed $obj, string $name): mixed {
        if (is_array($obj)) {
            return $obj[$name] ?? null;
        }
        if (is_object($obj)) {
            $getter = 'get' . ucfirst($name);
            if (method_exists($obj, $getter)) {
                return $obj->$getter();
            }
            if (isset($obj->$name)) {
                return $obj->$name;
            }
        }
        return null;
    }

    private static function escapeAttr(string $value): string {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
