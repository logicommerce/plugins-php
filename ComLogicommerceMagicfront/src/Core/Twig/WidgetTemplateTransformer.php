<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

/**
 * Entry point for transforming widget Twig templates before rendering.
 *
 * Slot rendering used to be a compile-time placeholder substitution
 * (`{{ mff_widget_slot }}` / `{{ mff_widget_slot('id') }}`) that also injected
 * `mff-widget-slot-root="1"` on the slot's HTML ancestor. That whole layer is
 * gone: slot rendering is now the `mff_widget_slot()` Twig function in
 * {@see MagicfrontTwigFunctions} and widget JSON authors write the slot-root
 * attribute themselves. This transformer therefore only does the
 * non-placeholder transforms that still apply to every widget template.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class WidgetTemplateTransformer {

    public static function transform(string $templateHtml): string {
        $tpl = str_replace(["\r\n", "\r"], "\n", $templateHtml);
        return ChildIndexInjector::inject($tpl);
    }
}
