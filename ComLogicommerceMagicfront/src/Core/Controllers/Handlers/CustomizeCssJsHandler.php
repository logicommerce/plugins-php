<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Response;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

/**
 * Builds the per-page widget CSS/JS in three output shapes selected by the
 * `type` request parameter:
 *
 *  - `customizeCssJs` → JSON `{css, js}` for canvas hot-reload.
 *  - `customizeCss`   → raw `text/css` for storefront `<link rel="stylesheet">`.
 *  - `customizeJs`    → raw `application/javascript` for storefront `<script src>`.
 *
 * No server-side caching: every request runs the full pipeline (dcsapi
 * fetch + generators). Emits `Cache-Control: no-store` so browsers /
 * intermediate caches don't keep stale copies either.
 */
class CustomizeCssJsHandler extends AbstractCustomizeHandler {

    use CssGeneratorTrait;
    use JsGeneratorTrait;

    private const SUPPORTED_TYPES = [
        FunctionType::CUSTOMIZE_CSS_JS,
        FunctionType::CUSTOMIZE_CSS,
        FunctionType::CUSTOMIZE_JS,
    ];

    private string $resolvedType = '';

    public function supports(string $type): bool {
        if (in_array($type, self::SUPPORTED_TYPES, true)) {
            $this->resolvedType = $type;
            return true;
        }
        return false;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return match ($this->resolvedType) {
            FunctionType::CUSTOMIZE_CSS => 'text/css; charset=' . \CHARSET,
            FunctionType::CUSTOMIZE_JS  => 'application/javascript; charset=' . \CHARSET,
            default                     => 'application/json; charset=' . \CHARSET,
        };
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        Response::addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

        $token = MagicfrontToken::getToken();
        if (empty($token) || !$this->isValidToken($token)) {
            return $this->slice($this->emptyData());
        }

        $pageId   = $controller->getRequestParamValue(Parameters::PAGE, false);
        $language = $controller->getRequestParamValue(Parameters::LANGUAGE, false)
            ?? Language::getInstance()->getLanguage();

        if (empty($pageId) || !$this->isValidPageId($pageId)) {
            return $this->slice($this->emptyData());
        }

        return $this->slice($this->buildOutput($pageId, $language));
    }

    /**
     * Expensive path: dcsapi fetch + CSS/JS generators.
     *
     * @return array{css: string, js: string}
     */
    private function buildOutput(string $pageId, string $language): array {
        $widgets     = $this->getPageWidgets($pageId, $language);
        $widgetTypes = $this->collectWidgetTypes($widgets);

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
     * @param array{css: string, js: string} $data
     */
    private function slice(array $data): string {
        return match ($this->resolvedType) {
            FunctionType::CUSTOMIZE_CSS => $data['css'],
            FunctionType::CUSTOMIZE_JS  => $data['js'],
            default                     => json_encode($data),
        };
    }

    /**
     * @return array{css: string, js: string}
     */
    private function emptyData(): array {
        return [
            'css' => "/* Magic front Custom CSS: No widgets */\n",
            'js'  => "// Magic front Custom JavaScript: No widgets\n",
        ];
    }
}
