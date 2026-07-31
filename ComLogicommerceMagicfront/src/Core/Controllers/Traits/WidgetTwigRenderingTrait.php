<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\Resources\Utils;
use FWK\Core\Theme\Theme;
use FWK\Twig\TwigLoader;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Twig\PluginTwigBootstrap;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;

/**
 * Shared widget → HTML rendering for plugin-route handlers: builds the widget Twig environment
 * (plugin globals + template list) and renders a widget template. Storefront-safe — no dcsapi,
 * no auth — so both the editor GetWidget path and storefront content handlers reuse it.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait WidgetTwigRenderingTrait {

    /**
     * Extract templateHtml from already-fetched templates, filtered to the given types.
     *
     * @param  string[]                    $types
     * @param  array<string, WidgetTemplate> $allTemplates
     * @return array<string, string>       HTML indexed by type
     */
    protected function buildWidgetTemplateList(array $types, array $allTemplates): array {
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

    /**
     * @param array<string, string> $widgetTemplateList
     */
    protected function buildTwigEnvironment(
        ComLogicommerceMagicfrontController $controller,
        array $widgetTemplateList
    ): \Twig\Environment {
        $twig = new TwigLoader(Theme::getInstance());
        $twig->load([], 0, true);

        $controller->addWidgetTwigBaseFunctions($twig);
        $controller->addWidgetTwigBaseExtensions($twig);

        $twigEnv  = $twig->getTwigEnvironment();
        $pluginDir = Utils::getCamelFromSnake(ComLogicommerceMagicfrontController::PLUGIN_MODULE, '.');
        $pharPath = \Phar::running();
        if (strlen($pharPath) === 0) {
            $pharPath = PLUGINS_LOAD_PATH . '/' . $pluginDir;
        }
        $twigCoreTemplatesPath = $pharPath . '/twigCoreTemplates';

        if (is_dir($twigCoreTemplatesPath)) {
            $twigEnv->getLoader()->addPath($twigCoreTemplatesPath);
        }

        PluginTwigBootstrap::apply($twigEnv, ContextBuilder::fromSession());

        foreach ($controller->getDefaultDataForWidgetRender() as $key => $value) {
            $twigEnv->addGlobal($key, $value);
        }

        $twigEnv->addGlobal(MagicfrontControllerData::WIDGET_TEMPLATE_LIST, $widgetTemplateList);

        return $twigEnv;
    }

    /**
     * @param array<string, string> $widgetTemplateList
     * @param array<string, mixed>  $twigData
     */
    protected function renderWidgetHtml(
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
}
