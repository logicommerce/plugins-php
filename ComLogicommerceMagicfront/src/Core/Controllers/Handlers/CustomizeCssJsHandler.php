<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

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
        // Request-param validation gates stay — these guard against malformed
        // input, not against API response data. API errors propagate uncaught.
        $token = $controller->getRequestParamValue(Parameters::TOKEN, false);
        if (empty($token) || !$this->isValidToken($token)) {
            return $this->emptyResponse();
        }

        $pageId   = $controller->getRequestParamValue(Parameters::PAGE, false);
        $language = $controller->getRequestParamValue(Parameters::LANGUAGE, false)
            ?? Language::getInstance()->getLanguage();

        $widgets     = [];
        $widgetTypes = [];
        if (!empty($pageId) && $this->isValidPageId($pageId)) {
            $widgets     = $this->getPageWidgets($token, $pageId, $language);
            $widgetTypes = $this->collectWidgetTypes($widgets);
        }

        // Legitimate empty state: page has no widgets → no CSS/JS to emit.
        if (empty($widgetTypes)) {
            return $this->emptyResponse();
        }

        $templates = WidgetsService::getInstance()
            ->setToken($token)
            ->getWidgetTemplatesForTypes($widgetTypes);

        return json_encode([
            'css' => $this->generateCss($widgets, $templates),
            'js'  => $this->generateJs($templates),
        ]);
    }

    private function emptyResponse(): string {
        return json_encode([
            'css' => "/* Magic front Custom CSS: No widgets */\n",
            'js'  => "// Magic front Custom JavaScript: No widgets\n",
        ]);
    }
}
