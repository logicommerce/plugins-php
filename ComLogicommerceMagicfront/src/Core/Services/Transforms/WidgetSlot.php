<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Dispatches both slot-placeholder flavors in widget Twig templates:
 *
 *   1. Bare `{{ mff_widget_slot }}` — columns-style, iterates `page.subpages`.
 *      Delegated to {@see BareSlotInjector}, which adds
 *      `mff-widget-slot-root="1"` on the 2nd HTML parent (case A) or wraps
 *      with a new `<div>` (cases B/C). The PlaceholderReplacer then inlines
 *      `mff-widget-slot.twig`.
 *
 *   2. Parametric `{{ mff_widget_slot('id') }}` — fixed slot. Delegated to
 *      {@see ParametricSlotExpander}, which rewrites every placeholder into
 *      a filter-by-slotId Twig block. No slot-root attribute is injected —
 *      slot children render as real widgets with canvas-visible MFF markers.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services\Transforms
 */
final class WidgetSlot {

    public const WIDGET_SLOT_ROOT = 'mff-widget-slot-root';

    public static function inject(string $tpl): string {
        // Parametric placeholders are rewritten first. After rewrite, any
        // remaining bare placeholders get the slot-root injection below.
        $tpl = ParametricSlotExpander::expand($tpl);
        if (!str_contains($tpl, WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value)) {
            return $tpl;
        }
        return BareSlotInjector::inject($tpl);
    }
}
