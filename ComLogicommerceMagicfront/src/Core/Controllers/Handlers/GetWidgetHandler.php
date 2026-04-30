<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Utils;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use FWK\Twig\TwigLoader;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\CssGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\JsGeneratorTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;

class GetWidgetHandler extends AbstractCustomizeHandler {

    use CssGeneratorTrait;
    use JsGeneratorTrait;

    public function supports(string $type): bool {
        return $type === FunctionType::GET_WIDGET;
    }

    public function isRawResponse(): bool {
        return true;
    }

    public function getRawResponseContentType(): ?string {
        return 'application/json; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $token    = $controller->getRequestParamValue(Parameters::TOKEN, true);
        $pageId   = $controller->getRequestParamValue(Parameters::PAGE, true);
        $widgetId = $controller->getRequestParamValue(Parameters::WIDGET_ID, true);
        $language = Language::getInstance()->getLanguage();

        try {
            $service = WidgetsService::getInstance()->setToken($token);

            $widget       = $service->getPageWidgetById($pageId, $widgetId, $language);
            $neededTypes  = WidgetTypeCollector::fromPages([$widget]);

            $templates          = $service->getWidgetTemplatesForTypes($neededTypes);
            $widgetTemplateList = $this->buildWidgetTemplateList($neededTypes, $templates);
            $html               = $this->renderWidget($controller, $widget, $widgetTemplateList);

            // CSS/JS for this widget's types only — no page-wide widget list needed.
            $css = $this->generateCss([], $templates);
            $js  = $this->generateJs($templates);

            // Wrap in { data: {...} } to match the envelope FWK adds for DTO responses,
            // which the canvas client (widget.ts) unwraps via `root.data`.
            return json_encode(['data' => [
                'success'  => true,
                'widgetId' => $widgetId,
                'type'     => $widget->getCustomType(),
                'html'     => $html,
                'css'      => $css,
                'js'       => $js,
            ]]);
        } catch (\Throwable $e) {
            return json_encode(['data' => [
                'success'      => false,
                'widgetId'     => $widgetId,
                'messageError' => $e->getMessage(),
            ]]);
        }
    }

    // ─── Template resolution ──────────────────────────────────────────────────

    /**
     * Extract templateHtml from already-fetched templates, filtered to the given types.
     *
     * @param  string[]        $types        Widget types needed for this widget's tree
     * @param  WidgetTemplate[] $allTemplates Templates indexed by type
     * @return array<string, string>         HTML indexed by type (transforms already applied by the DTO)
     */
    private function buildWidgetTemplateList(array $types, array $allTemplates): array {
        $list = [];
        foreach ($types as $type) {
            $template = $allTemplates[$type] ?? null;
            if ($template === null) {
                continue;
            }
            $html = $template->getTemplateHtml();
            if ($html !== '') {
                $list[$type] = $html;
            }
        }
        return $list;
    }

    // ─── Rendering ────────────────────────────────────────────────────────────

    private function renderWidget(
        ComLogicommerceMagicfrontController $controller,
        PluginPage $widget,
        array $widgetTemplateList
    ): string {
        $widgetId   = $widget->getDraftId() ?: $widget->getId();
        $widgetType = $widget->getCustomType();

        $twigEnv = $this->buildTwigEnvironment($controller, $widgetTemplateList);
        $html    = $this->renderWidgetHtml($twigEnv, $widgetType, $widgetTemplateList, [
            'page'            => $widget,
            'moduleType'      => $widgetType,
            'moduleSettings'  => $widget->getModuleSettings(),
            'widgetId'        => $widgetId,
            'version'         => Theme::getInstance()->getVersion(),
        ]);

        return $this->wrapWithMarkers($widgetId, $widgetType, $html);
    }

    private function buildTwigEnvironment(
        ComLogicommerceMagicfrontController $controller,
        array $widgetTemplateList
    ): \Twig\Environment {
        $twig = new TwigLoader(Theme::getInstance());
        $twig->load([], 0, true);

        $controller->addWidgetTwigBaseFunctions($twig);
        $controller->addWidgetTwigBaseExtensions($twig);

        $twigEnv  = $twig->getTwigEnvironment();
        $pluginDir = Utils::getCamelFromSnake(ComLogicommerceMagicfrontController::PLUGIN_MODULE, '.');
        $twigCoreTemplatesPath = PLUGINS_LOAD_PATH . '/' . $pluginDir . '/twigCoreTemplates';

        if (is_dir($twigCoreTemplatesPath)) {
            $twigEnv->getLoader()->addPath($twigCoreTemplatesPath);
        }

        foreach ($controller->getDefaultDataForWidgetRender() as $key => $value) {
            $twigEnv->addGlobal($key, $value);
        }

        $twigEnv->addGlobal('widgetTemplateList', $widgetTemplateList);

        return $twigEnv;
    }

    private function renderWidgetHtml(
        \Twig\Environment $twigEnv,
        string $widgetType,
        array $widgetTemplateList,
        array $twigData
    ): string {
        if (empty($widgetTemplateList[$widgetType])) {
            throw new \Exception("Template not found in API response for widget type: {$widgetType}");
        }
        return $twigEnv->createTemplate($widgetTemplateList[$widgetType])->render($twigData);
    }

    /**
     * Wrap rendered HTML in MFF_WIDGET_START / MFF_WIDGET_END comment markers
     * so the canvas can detect widget boundaries in the page source.
     */
    private function wrapWithMarkers(string $widgetId, string $widgetType, string $html): string {
        $payload = json_encode(
            ['type' => $widgetType, 'id' => $widgetId, 'draftId' => $widgetId],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        // Escape closing comment sequence to prevent HTML injection
        $payload = str_replace('-->', '--\\u003E', $payload);

        return "<!-- MFF_WIDGET_START {$payload} -->{$html}<!-- MFF_WIDGET_END -->";
    }
}
