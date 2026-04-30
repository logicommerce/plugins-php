<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;

/**
 * JavaScript pass-through from widget templates.
 * Each template's `templateJs` is emitted verbatim — no IIFE wrapping,
 * no comments, no type-name sanitization. The API is the source of truth.
 */
trait JsGeneratorTrait {

    /**
     * @param WidgetTemplate[] $templates Templates indexed by type.
     */
    protected function generateJs(array $templates): string {
        return implode("\n", array_map(
            static fn(WidgetTemplate $t): string => $t->getTemplateJs(),
            $templates
        ));
    }
}
