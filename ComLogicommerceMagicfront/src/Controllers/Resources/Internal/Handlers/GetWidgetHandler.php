<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers;

use FWK\Core\Resources\Language;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetRender;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use FWK\Enums\Parameters;
use FWK\Twig\TwigLoader;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use SDK\Core\Dtos\Element;

class GetWidgetHandler extends AbstractPluginRouteHandler {

    public function supports(string $type): bool {
        return $type === FunctionType::GET_WIDGET;
    }

    public function handle(ComLogicommerceMagicfrontController $controller): ?Element {
        $dcsToken = $controller->getRequestParamValue(Parameters::DCS_TOKEN, true);
        $dcsPageId = $controller->getRequestParamValue(Parameters::DCS_PAGE_ID, true);
        $widgetId = $controller->getRequestParamValue(Parameters::WIDGET_ID, true);

        try {
            $widget = WidgetsService::getInstance()->getPageWidgetById($dcsPageId, $widgetId, $dcsToken, Language::getInstance()->getLanguage());
            // Get widgetTemplateList via API, same as HomeController
            $widgetTemplateList = WidgetsService::getInstance()->getWidgetTemplatesAsHtml($dcsToken);

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

        $twig = new TwigLoader(Session::getInstance()->getDefaultTheme());
        $twig->load($twigData, 0, true);

        $controller->addWidgetTwigBaseFunctions($twig);
        $controller->addWidgetTwigBaseExtensions($twig);

        $twigEnv = $twig->getTwigEnvironment();
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
            '<!-- DCS_WIDGET_START %s -->%s<!-- DCS_WIDGET_END -->',
            $json,
            $widgetHtml
        );
    }
}
