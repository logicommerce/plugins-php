<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;

/**
 * Runs the SAME CSS+JS generators the editor canvas uses. Pure — caller hands
 * widgets + templates, gets `{css, js}` back. No knowledge of where the data
 * came from (storefront blob, dcsapi, anywhere).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
final class WidgetAssetsBuilder {
    use CssGeneratorTrait;
    use JsGeneratorTrait;

    /**
     * @param  WidgetInstance[]              $widgets   Flat list (via WidgetTypeCollector::flatten).
     * @param  array<string, WidgetTemplate> $templates Templates keyed by type.
     * @return array{css: string, js: string}
     */
    public function build(array $widgets, array $templates): array {
        if ($widgets === [] || $templates === []) {
            return ['css' => '', 'js' => ''];
        }
        return [
            'css' => $this->generateCss($widgets, $templates),
            'js'  => $this->generateJs($templates),
        ];
    }
}
