<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Core\Services\Transforms\PlaceholderReplacer;
use Plugins\ComLogicommerceMagicfront\Core\Services\Transforms\WidgetSlot;
use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Entry point for transforming widget twig templates before rendering.
 *
 * Applies two transformations in order:
 *   1. WidgetSlot  — injects mff-widget-slot-root="1" on the HTML parent of the slot block.
 *   2. PlaceholderReplacer — replaces {{ mff_widget_slot }} (and future placeholders) with twig includes.
 */
class WidgetTemplateTransformer {
    public static function transformAll(mixed $templates): mixed {
        if (is_string($templates)) {
            return self::transformString($templates);
        }

        if (is_array($templates)) {
            foreach ($templates as $k => $v) {
                $templates[$k] = self::transformAll($v);
            }
        }

        return $templates;
    }

    private static function transformString(string $tpl): string {
        $tpl = str_replace(["\r\n", "\r"], "\n", $tpl);

        if (str_contains($tpl, WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value) && !str_contains($tpl, WidgetSlot::WIDGET_SLOT_ROOT)) {
            $tpl = WidgetSlot::inject($tpl);
        }

        return PlaceholderReplacer::replace($tpl);
    }
}
