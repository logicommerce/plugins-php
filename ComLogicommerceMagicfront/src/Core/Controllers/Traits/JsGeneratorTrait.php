<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;

/**
 * JavaScript pass-through from widget templates.
 * Each template's `templateJs` is emitted verbatim — no IIFE wrapping,
 * plus, in the editor canvas only, its `previewJs` —
 * no comments, no type-name sanitization. The API is the source of truth.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait JsGeneratorTrait {

    /**
     * @param WidgetTemplate[] $templates Templates indexed by type.
     */
    protected function generateJs(array $templates): string {
        // `previewJs` is the widget's reaction to the editor's preview states: only the canvas runs it. The store
        // and the preview tab never receive it — simulation code does not ship to shoppers.
        $canvas = RenderMode::isCanvasMode();
        return implode("\n", array_map(
            static fn(WidgetTemplate $t): string => $canvas && $t->getPreviewJs() !== ''
                ? $t->getTemplateJs() . "\n" . $t->getPreviewJs()
                : $t->getTemplateJs(),
            $templates
        ));
    }
}
