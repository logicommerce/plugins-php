<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Twig\Environment;

/**
 * Render-time helper behind the `mff_widget_slot()` Twig function. Two output
 * shapes coexist because the old placeholder system had two distinct
 * behaviours that the unified function must keep:
 *
 *   - {@see self::renderSlotContainer()} — bare / columns style. Wraps the
 *     subpage in a `<div data-mff-widget-root="1"
 *     data-mff-sortable-container="1">` so canvas treats it as a sortable
 *     drop zone. The subpage's own children render recursively via the widget
 *     macro inside.
 *
 *   - {@see self::renderAsWidget()} — parametric / fixed-slot style. Renders
 *     the matched subpage through the standard widget macro
 *     (`<div class="mff-widget" data-mff-slot-id="...">`) so canvas sees it
 *     as a normal widget, not a drop zone.
 *
 * Subpages arrive as Page objects on the storefront fwk path and as plain
 * arrays on the docker template-renderer path (WidgetArrayTransformer keeps
 * data renderer-agnostic). The {@see self::field()} helper reads attributes
 * from both shapes — same accessor semantics Twig uses for `{{ obj.x }}`.
 *
 * Both modes funnel into {@see self::renderViaMacro()} for the actual
 * `template_from_string` invocation — that's the only renderer-agnostic way
 * to call the widget macro from PHP, and Twig caches the compiled stub by
 * source hash.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class WidgetSlotRenderer {

    /**
     * Visual placeholder rendered inside an empty slot under preview mode
     * (docker template-renderer, AI tooling). Tells the editor user "a widget
     * goes here" — the equivalent dashed box the old PlaceholderEngine
     * emitted. Storefront and AJAX paths never set previewMode=true, so
     * production / canvas-iframe rendering never shows this.
     */
    private const EMPTY_PREVIEW_HTML = '<div style="padding:16px;border:1px dashed #bbb;color:#999;font:12px/1 monospace;text-align:center;background:#f9f9f9;min-height:80px;display:flex;align-items:center;justify-content:center">slot content</div>';

    /**
     * Look up a child subpage by `slotId` inside `$context['page']`. Works
     * with both array and object pages so docker and storefront stay aligned.
     */
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

        $payload = json_encode([
            'type'       => $type,
            'id'         => $idAttr,
            'draftId'    => $draftId,
            'parentId'   => null,
            'label'      => null,
            'isSlotItem' => true,
        ], JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $subpages = self::field($subPage, 'subpages');
        $children = self::renderChildren($env, $context, is_array($subpages) ? $subpages : []);

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
        // Empty slot — in preview mode the editor needs a visible affordance
        // so the merchant sees where to drop a widget. previewMode comes
        // through $context only on the docker render path (the env's globals
        // expose it before macros run); storefront coreTwig is locked at
        // bootstrap time so $context['previewMode'] is undefined there, which
        // is fine — storefront is never preview anyway.
        return !empty($context['previewMode']) ? self::EMPTY_PREVIEW_HTML : '';
    }

    private static function renderViaMacro(Environment $env, array $context, array $pages): string {
        return $env->createTemplate(
            "{% import 'macros/widget.twig' as __mffSlotMacros %}"
            . '{{ __mffSlotMacros.widgets({pages: pages, version: version, widgetTemplateList: widgetTemplateList}) }}'
        )->render([
            'pages'              => $pages,
            'version'            => $context['version'] ?? '',
            'widgetTemplateList' => $context['widgetTemplateList'] ?? [],
        ]);
    }

    /**
     * Read a field from either an array or an object. Matches Twig's
     * `{{ obj.field }}` semantics so storefront Page DTOs and docker arrays
     * are interchangeable. Prefers `getX()` getters and only falls back to
     * direct property access for *visible* (public) properties — using
     * `property_exists` would tunnel into protected SDK fields like
     * `Page::$subpages` and trip PHP's visibility check.
     */
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
