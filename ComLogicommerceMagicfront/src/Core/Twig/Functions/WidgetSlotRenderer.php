<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Functions;

use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Twig\Environment;

/**
 * Render-time helper behind `mff_widget_slot()`. Two shapes:
 *  - renderSlotContainer(): bare/columns — sortable drop zone.
 *  - renderAsWidget(): parametric/fixed-slot — single widget block.
 */
final class WidgetSlotRenderer {

    /** Placeholder rendered for empty slots in preview mode (docker / AI tooling). */
    private const EMPTY_PREVIEW_HTML = '<div style="padding:16px;border:1px dashed #bbb;color:#999;font:12px/1 monospace;text-align:center;background:#f9f9f9;min-height:80px;display:flex;align-items:center;justify-content:center">slot content</div>';

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
            . '{{ __mffSlotMacros.widgets({pages: pages, version: version, widgetTemplateList: widgetTemplateList, permission: permission}) }}'
        )->render([
            'pages'              => $pages,
            'version'            => $context['version'] ?? '',
            MagicfrontControllerData::WIDGET_TEMPLATE_LIST => $context[MagicfrontControllerData::WIDGET_TEMPLATE_LIST] ?? [],
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
