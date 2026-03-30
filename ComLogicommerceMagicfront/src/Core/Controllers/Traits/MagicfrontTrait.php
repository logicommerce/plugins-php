<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\FilterInput\FilterInput;
use SDK\Core\Dtos\ElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;
use FWK\Enums\Services;
use FWK\Services\PageService;
use FWK\Services\PluginService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Resources\Environment;

/**
 * This is the Magicfront trait.
 *
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Traits
 */
trait MagicfrontTrait {

    public const BATCH_DATA_NAME_SOURCE = 'batchDataNameSource';

    public const PAGE_NOT_FOUND = 'PAGE_NOT_FOUND';

    public ?ElementCollection $pages = null;

    public ?PageService $pageService = null;

    public ?WidgetsService $widgetsService = null;

    public ?PluginService $pluginService = null;

    public ?PageParametersGroup $pageParametersGroup = null;

    public ?Route $route = null;

    public bool $useEndpointDragAndDrop = false;

    public ?String $token = null;

    public ?String $page = null;

    public bool $pluginMagicfrontEnabled = false;

    public bool $isMagicfrontEnabled = false;

    protected function getFilterParams(): array {
        return [
            Parameters::TOKEN => new FilterInput([
                FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
            ]),
            Parameters::PAGE => new FilterInput([
                FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
            ])
        ];
    }

    protected function magicfrontInit(Route $route, PageParametersGroup $pageParametersGroup): void {
        $this->pageService = Loader::service(Services::PAGE);
        $this->widgetsService = WidgetsService::getInstance();
        $this->pluginService = Loader::service(Services::PLUGIN);
        $this->pluginMagicfrontEnabled = $this->pluginService->isPluginMagicfrontEnabled($route->getType());

        $this->pageParametersGroup = $pageParametersGroup;

        $this->route = $route;

        $this->token = $this->getRequestParam(Parameters::TOKEN, false, null);
        $this->page = $this->getRequestParam(Parameters::PAGE, false, null);

        $this->isMagicfrontEnabled = $this->token ? true : false;
        $this->token = $this->token ?? $this->widgetsService->getToken("home");
        $this->page = $this->page ?? $this->widgetsService->getPageId($route->getId(), $this->token);
    }

    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if ($this->pluginMagicfrontEnabled && $this->token && $this->page) {
            $this->pages = $this->widgetsService->getPageWidgets($this->page, $this->route->getLanguage(), $this->token);
        }

        $this->useEndpointDragAndDrop = $this->pages != null && is_null($this->pages?->getError());

        /*if (!$this->useEndpointDragAndDrop) {
            // Fallback to FOB
            $this->pageService->addGetPages($requests, self::BATCH_DATA_NAME_SOURCE, $this->pageParametersGroup);
        }*/
    }

    protected function setMagicfrontData(): void {
        /*if (!$this->useEndpointDragAndDrop) {
            // Fallback: load pages from FOB batch data
            $this->pages = $this->getControllerData(self::BATCH_DATA_NAME_SOURCE);
        }*/

        // Apply relation resolver for products/categories enrichment
        $this->pages = PageRelationResolver::setData($this->pages);
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTemplateList = [];

        if ($this->pluginMagicfrontEnabled && $this->token) {
            $widgetTemplateList = $this->widgetsService?->getWidgetTemplatesAsHtml($this->token) ?? [];
        }
        $this->setDataValue('widgetTemplateList', $widgetTemplateList);
        $this->setDataValue('token', $this->token);
        $this->setDataValue('page', $this->page);
        $this->setDataValue('isMagicfrontEnabled', $this->isMagicfrontEnabled);
        $this->setDataValue('mgfAssetsUrl', Environment::get('MGF_ASSETS_URL'));
    }

    protected function collectPageWidgetTypes(array $pages): array {
        $types = [];

        foreach ($pages as $page) {
            $customType = '';
            $subpages = [];

            if (is_array($page)) {
                $customType = $page['customType'] ?? '';
                $subpages = $page['subpages'] ?? [];
            } elseif (is_object($page)) {
                if (method_exists($page, 'getCustomType')) {
                    $customType = $page->getCustomType();
                }
                if (method_exists($page, 'getSubpages')) {
                    $subpages = $page->getSubpages() ?? [];
                }
            }

            if (is_string($customType) && $customType !== '') {
                $types[] = $customType;
            }

            if (!empty($subpages)) {
                $types = array_merge($types, $this->collectPageWidgetTypes(is_array($subpages) ? $subpages : []));
            }
        }

        return array_values(array_unique($types));
    }
}
