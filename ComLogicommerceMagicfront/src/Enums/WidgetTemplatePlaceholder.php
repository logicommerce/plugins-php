<?php

namespace Plugins\ComLogicommerceMagicfront\Enums;

/**
 * Maps each widget template placeholder to its twig include file.
 * Include files live in twigCoreTemplates/includes/.
 *
 * To add a new placeholder:
 *   1. Add a case here with the placeholder string as value.
 *   2. Add the corresponding .twig file in twigCoreTemplates/includes/.
 */
enum WidgetTemplatePlaceholder: string {
    case MFF_WIDGET_SLOT = '{{ mff_widget_slot }}';

    public function include(): string {
        return match($this) {
            self::MFF_WIDGET_SLOT => 'mff-widget-slot.twig',
        };
    }
}
