<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Utils;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Core\Dtos\WidgetRender;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;
use FWK\Twig\TwigLoader;
use FWK\Core\Theme\Theme;
use SDK\Core\Dtos\Element;

class GetWidgetHandler extends AbstractPluginRouteHandler {

    public function supports(string $type): bool {
        return $type === FunctionType::GET_WIDGET;
    }

    public function handle(ComLogicommerceMagicfrontController $controller): ?Element {
        $token = $controller->getRequestParamValue(Parameters::TOKEN, true);
        $pageId = $controller->getRequestParamValue(Parameters::PAGE, true);
        $widgetId = $controller->getRequestParamValue(Parameters::WIDGET_ID, true);

        try {
            $widget = WidgetsService::getInstance()->getPageWidgetById($pageId, $widgetId, $token, Language::getInstance()->getLanguage());
            // Get widgetTemplateList via API, same as HomeController
            $widgetTemplateList = WidgetsService::getInstance()->getWidgetTemplatesAsHtml($token);

            $html = $this->renderWidget($controller, $widget, $widgetTemplateList);
            return new WidgetRender(true, $html, $widgetId, $widget->getCustomType(), '');
        } catch (\Exception $e) {
            return new WidgetRender(false, '', $widgetId, '', $e->getMessage());
        }
    }

    private function renderWidget(ComLogicommerceMagicfrontController $controller, PluginPage $widget, array $widgetTemplateList = []): string {
        $widgetId = $widget->getDraftId() ?: $widget->getId();
        $twigData = [
            'page' => $widget,
            'moduleType' => $widget->getCustomType(),
            'moduleSettings' => $widget->getModuleSettings(),
            'widgetId' => $widgetId,
            'version' => Theme::getInstance()->getVersion(),
        ];

        $twig = new TwigLoader(Theme::getInstance());
        $twig->load($twigData, 0, true);

        $controller->addWidgetTwigBaseFunctions($twig);
        $controller->addWidgetTwigBaseExtensions($twig);

        $twigEnv = $twig->getTwigEnvironment();
        $pluginDir = Utils::getCamelFromSnake(ComLogicommerceMagicfrontController::PLUGIN_MODULE, '.');
        $twigCoreTemplatesPath = PLUGINS_LOAD_PATH . '/' . $pluginDir . '/twigCoreTemplates';
        if (is_dir($twigCoreTemplatesPath)) {
            $twigEnv->getLoader()->addPath($twigCoreTemplatesPath);
        }
        foreach ($controller->getDefaultDataForWidgetRender() as $key => $value) {
            $twigEnv->addGlobal($key, $value);
        }

        // Add widgetTemplateList as global variable for nested widgets support
        $twigEnv->addGlobal('widgetTemplateList', $widgetTemplateList);

        try {
            // Use template from API via template_from_string
            $widgetType = $widget->getCustomType();
            if (empty($widgetTemplateList[$widgetType])) {
                throw new \Exception("Template not found in API response for widget type: {$widgetType}");
            }

            $templateString = $widgetTemplateList[$widgetType];
            $template = $twigEnv->createTemplate($templateString);
            $widgetHtml = $template->render($twigData);
        } catch (\Exception $e) {
            $widgetHtml = sprintf(
                '<div class="widget-render-error">Error rendering widget type "%s": %s</div>',
                htmlspecialchars($widget->getCustomType(), ENT_QUOTES),
                htmlspecialchars($e->getMessage(), ENT_QUOTES)
            );
        }

        $payload = [
            'type' => $widget->getCustomType(),
            'id' => $widgetId,
            'draftId' => $widgetId,
            'label' => method_exists($widget, 'getLabel') ? $widget->getLabel() : null,
            'parentId' => method_exists($widget, 'getParentId') ? $widget->getParentId() : null,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return sprintf(
            '<!-- MFF_WIDGET_START %s -->%s<!-- MFF_WIDGET_END -->',
            $json,
            $widgetHtml
        );
    }
}
