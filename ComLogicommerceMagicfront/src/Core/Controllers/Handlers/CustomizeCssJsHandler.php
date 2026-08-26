<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Response;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

/**
 * Builds the per-page widget CSS+JS for the editor canvas hot-reload — type
 * `customizeCssJs` returns `{css, js}` as JSON for the canvas runtime to swap
 * in without a full page reload.
 *
 * Storefront does NOT use this endpoint: MagicfrontTrait inlines `<style>` /
 * `<script>` straight into the page via WidgetAssetsBuilder, with no extra
 * HTTP round-trip.
 *
 * No server-side caching: every request runs the full pipeline (dcsapi
 * fetch + generators). Emits `Cache-Control: no-store` so the canvas always
 * sees the just-edited state.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
class CustomizeCssJsHandler extends AbstractCustomizeHandler {

    use CssGeneratorTrait;
    use JsGeneratorTrait;

    public function supports(string $type): bool {
        return $type === FunctionType::CUSTOMIZE_CSS_JS;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return 'application/json; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        // Canvas hot-reload needs fresh data on every request, not the cached
        // last-published state.
        WidgetsService::getInstance()->disableCache();
        Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $pageId   = $controller->getRequestParamValue(Parameters::PAGE, false);
        $language = $controller->getRequestParamValue(Parameters::LANGUAGE, false)
            ?? Language::getInstance()->getLanguage();

        if (empty($pageId) || !$this->isValidPageId($pageId)) {
            return json_encode($this->emptyData());
        }
        return json_encode($this->buildOutput($pageId, $language));
    }

    /**
     * @return array
     */
    private function buildOutput(string $pageId, string $language): array {
        $widgets     = $this->getPageWidgets($pageId, $language);
        $widgetTypes = WidgetTypeCollector::fromWidgets($widgets);
        if (empty($widgetTypes)) {
            return $this->emptyData();
        }
        $templates = WidgetsService::getInstance()->getWidgetTemplatesForTypes($widgetTypes);
        return [
            'css' => $this->generateCss($widgets, $templates),
            'js'  => $this->generateJs($templates),
        ];
    }

    /**
     * @return array
     */
    private function emptyData(): array {
        return [
            'css' => "/* Magic front Custom CSS: No widgets */\n",
            'js'  => "// Magic front Custom JavaScript: No widgets\n",
        ];
    }
}
