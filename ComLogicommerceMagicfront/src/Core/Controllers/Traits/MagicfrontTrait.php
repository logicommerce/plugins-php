<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\FilterInput\FilterInput;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Services\PluginService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
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

    private const MFF_PREVIEW_PARAM = 'mff_preview';

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $token = null;

    protected ?string $page = null;

    protected bool $pluginMagicfrontEnabled = false;

    protected bool $isMagicfrontEnabled = false;

    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            Parameters::TOKEN        => new FilterInput($noMod),
            Parameters::PAGE         => new FilterInput($noMod),
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;
        $this->widgetsService = WidgetsService::getInstance();

        /** @var PluginService $pluginService */
        $pluginService = Loader::service(Services::PLUGIN);
        $this->pluginMagicfrontEnabled = $pluginService->isPluginMagicFrontEnabled($route->getType());

        $this->token = $this->getRequestParam(Parameters::TOKEN, false, null);
        $this->page  = $this->getRequestParam(Parameters::PAGE, false, null);

        $this->isMagicfrontEnabled = !empty($this->token);
        if ($this->token !== null && $this->token !== '') {
            $this->widgetsService->setToken($this->token);
        }

        $this->page = $this->page
            ?? ($this->token !== null && $this->token !== '' ? $this->widgetsService->getPageId((string)$route->getId()) : null);
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

        $this->setDataValue('widgetTemplateList', $widgetTemplateList);
        $this->setDataValue('widgetTypes', $widgetTypes);
        $this->setDataValue('token', $this->token);
        $this->setDataValue('page', $this->page);
        $this->setDataValue('isMagicfrontEnabled', $this->isMagicfrontEnabled);
        $this->setDataValue('mgfAssetsUrl', Environment::get('MGF_ASSETS_URL'));
    }
}
