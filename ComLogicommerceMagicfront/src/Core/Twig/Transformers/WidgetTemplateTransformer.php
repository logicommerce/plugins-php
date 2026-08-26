<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Transformers;

/** Entry point for compile-time transforms on widget Twig templates. */
/**
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig\Transformers
 */
class WidgetTemplateTransformer {

    public static function transform(string $templateHtml): string {
        $tpl = str_replace(["\r\n", "\r"], "\n", $templateHtml);
        return ChildIndexInjector::inject($tpl);
    }
}
