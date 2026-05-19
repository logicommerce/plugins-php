<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\FilterInput\FilterInput;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Services\PluginService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontSession;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Resources\Environment;
use SDK\Dtos\Common\Route;

/**
 * Mixes Magicfront-aware batch/data hooks into HTML controllers (Home,
 * Page\Module). Controllers extending the FWK base classes call
 * magicfrontInit() in their constructor, then delegate their setBatchData
 * and setData to setMagicfrontBatchData / setMagicfrontData.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait MagicfrontTrait {

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $token = null;

    protected ?string $page = null;

    protected bool $pluginMagicfrontEnabled = false;

    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            MagicfrontSession::MF_TOKEN => new FilterInput($noMod),
            Parameters::PAGE         => new FilterInput($noMod),
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;
        $this->widgetsService = WidgetsService::getInstance();

        /** @var PluginService $pluginService */
        $pluginService = Loader::service(Services::PLUGIN);
        $this->pluginMagicfrontEnabled = $pluginService->isPluginMagicFrontEnabled($route->getType());

        $this->token = MagicfrontSession::setToken($this->getRequestParam(MagicfrontSession::MF_TOKEN, false, null));
        $this->page  = $this->getRequestParam(Parameters::PAGE, false, null);

        $this->page = $this->page
            ?? (!empty($this->token) ? $this->widgetsService->getPageId((string)$route->getId()) : null);
    }

    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if (!$this->pluginMagicfrontEnabled || !$this->token || !$this->page) {
            return;
        }
        $this->pages = $this->widgetsService->getPageWidgets($this->page, $this->route->getLanguage());
    }

    protected function setMagicfrontData(): void {
        $this->pages = PageRelationResolver::setData($this->pages);
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTemplateList = [];
        $widgetTypes        = [];

        if ($this->pluginMagicfrontEnabled && $this->token && $this->pages !== null) {
            $widgetTypes = WidgetTypeCollector::fromPages($this->pages->getItems() ?? []);
            if ($widgetTypes !== []) {
                foreach ($this->widgetsService->getWidgetTemplatesForTypes($widgetTypes) as $type => $template) {
                    $widgetTemplateList[$type] = $template->getTemplateHtml();
                }
            }
        }

        $canvasMode = MagicfrontUtils::isCanvasMode();
        $showAssets = !$canvasMode && !empty($this->page) && !empty($widgetTypes);
        $language   = $this->route->getLanguage();

        $this->setDataValue('widgetTemplateList', $widgetTemplateList);
        $this->setDataValue('widgetTypes', $widgetTypes);
        $this->setDataValue('page', $this->page);
        $this->setDataValue('mfAssetsUrl', Environment::get('MF_ASSETS_URL'));
        $this->setDataValue('mfCanvasMode', $canvasMode);
        $this->setDataValue('mfShowAssets', $showAssets);
        $this->setDataValue('mfCustomCssUrl', $showAssets ? MagicfrontUtils::storefrontUrl(FunctionType::CUSTOMIZE_CSS, $this->page, $language) : null);
        $this->setDataValue('mfCustomJsUrl',  $showAssets ? MagicfrontUtils::storefrontUrl(FunctionType::CUSTOMIZE_JS,  $this->page, $language) : null);
    }
}
