<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;

/**
 * Runs the SAME CSS+JS generators the editor canvas uses. Pure — caller hands
 * widgets + templates, gets `{css, js}` back. No knowledge of where the data
 * came from (storefront blob, dcsapi, anywhere).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
class WidgetAssetsBuilder {
    use CssGeneratorTrait;
    use JsGeneratorTrait;

    /**
     * @param  WidgetInstance[]              $widgets   Flat list (via WidgetTypeCollector::flatten).
     * @param  array $templates Templates keyed by type.
     * @return array
     */
    public function build(array $widgets, array $templates): array {
        if ($widgets === [] || $templates === []) {
            return ['css' => '', 'js' => ''];
        }
        return [
            'css' => $this->generateCss($widgets, $templates),
            'js'  => $this->generateJs(self::presentTemplates($widgets, $templates)),
        ];
    }

    /**
     * The templates the widget list actually instantiates, each once. A chrome blob's schema carries
     * every family of BOTH regions, so emitting it whole would ship the footer's JS inside the header
     * (and run every module twice); a family-keyed schema resolved through several versions must not
     * repeat a template either.
     *
     * @param  WidgetInstance[] $widgets
     * @param  array            $templates
     * @return WidgetTemplate[]
     */
    private static function presentTemplates(array $widgets, array $templates): array {
        $present = [];
        foreach (WidgetTypeCollector::templateKeysFromWidgets($widgets) as $key) {
            $template = WidgetTypeCollector::resolveByKey($templates, $key);
            if ($template instanceof WidgetTemplate) {
                $present[spl_object_id($template)] = $template;
            }
        }
        return array_values($present);
    }
}
