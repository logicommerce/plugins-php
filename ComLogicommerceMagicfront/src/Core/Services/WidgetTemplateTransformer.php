<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Core\Services\Transforms\ChildIndexInjector;
use Plugins\ComLogicommerceMagicfront\Core\Services\Transforms\PlaceholderReplacer;
use Plugins\ComLogicommerceMagicfront\Core\Services\Transforms\WidgetSlot;
use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Entry point for transforming widget Twig templates before rendering.
 *
 * Applies transformations in order:
 *   1. ChildIndexInjector  — auto-injects data-mff-child-index on the first
 *      HTML element inside {% for ... in page.subpages %} blocks.
 *   2. WidgetSlot          — injects mff-widget-slot-root="1" on the HTML parent
 *      of the slot block (bare form) or rewrites parametric slot placeholders.
 *   3. PlaceholderReplacer — replaces {{ mff_widget_slot }} (and future
 *      placeholders) with their Twig include.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class WidgetTemplateTransformer {

    public static function transform(string $templateHtml): string {
        $tpl = str_replace(["\r\n", "\r"], "\n", $templateHtml);
        $tpl = ChildIndexInjector::inject($tpl);

        // Detect both bare `{{ mff_widget_slot }}` and parametric
        // `{{ mff_widget_slot('id') }}` forms. Either kind kicks off
        // WidgetSlot::inject (the bare skips when slot-root is already
        // injected; the parametric path always runs its expansion).
        $hasBare     = str_contains($tpl, WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value);
        $hasParam    = preg_match('/\{\{-?\s*mff_widget_slot\s*\(/', $tpl) === 1;
        $hasSlotRoot = str_contains($tpl, WidgetSlot::WIDGET_SLOT_ROOT);
        if ($hasParam || ($hasBare && !$hasSlotRoot)) {
            $tpl = WidgetSlot::inject($tpl);
        }

        return PlaceholderReplacer::replace($tpl);
    }
}
