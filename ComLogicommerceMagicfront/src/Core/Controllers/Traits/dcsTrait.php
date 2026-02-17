<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\FilterInput\FilterInput;
use SDK\Core\Dtos\ElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Parameters;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;
use FWK\Enums\Services;
use FWK\Services\PageService;
use FWK\Services\PluginService;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Resources\Environment;

/**
 * This is the DCS trait.
 *
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Traits
 */
trait dcsTrait {

    public const BATCH_DATA_NAME_SOURCE = 'batchDataNameSource';

    public const DYNAMIC_MODULES_CSS = 'dynamicModulesCss';

    public const PAGE_NOT_FOUND = 'PAGE_NOT_FOUND';

    public ?ElementCollection $pages = null;

    public ?PageService $pageService = null;

    public ?WidgetsService $widgetsService = null;

    public ?PluginService $pluginService = null;

    public ?PageParametersGroup $pageParametersGroup = null;

    public ?Route $route = null;

    public bool $useEndpointDragAndDrop = false;

    public ?String $dcsToken = null;

    public ?String $dcsPageId = null;

    public bool $pluginDcsEnabled = false;

    public bool $isDcsEnabled = false;

    protected function getFilterParams(): array {
        return [
            Parameters::DCS_TOKEN => new FilterInput([
                FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
            ]),
            Parameters::DCS_PAGE_ID => new FilterInput([
                FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
            ])
        ];
    }

    protected function dcsInit(Route $route, PageParametersGroup $pageParametersGroup): void {
        $this->pageService = Loader::service(Services::PAGE);
        $this->widgetsService = WidgetsService::getInstance();
        $this->pluginService = Loader::service(Services::PLUGIN);
        $this->pluginDcsEnabled = $this->pluginService->isPluginMagicfrontEnabled($route->getType());

        $this->pageParametersGroup = $pageParametersGroup;

        $this->route = $route;

        $this->dcsToken = $this->getRequestParam(Parameters::DCS_TOKEN, false, null);
        $this->dcsPageId = $this->getRequestParam(Parameters::DCS_PAGE_ID, false, null);

        $this->isDcsEnabled = $this->dcsToken ? true : false;
        $this->dcsToken = $this->dcsToken ?? $this->widgetsService->getDcsToken("home");
        $this->dcsPageId = $this->dcsPageId ?? $this->widgetsService->getPageId($route->getId(), $this->dcsToken);
    }

    protected function setDcsBatchData(BatchRequests $requests): void {
        if ($this->pluginDcsEnabled && $this->dcsToken && $this->dcsPageId) {
            $this->pages = $this->widgetsService->getPageWidgets($this->dcsPageId, $this->route->getLanguage(), $this->dcsToken);
        }

        $this->useEndpointDragAndDrop = $this->pages != null && is_null($this->pages?->getError());

        /*if (!$this->useEndpointDragAndDrop) {
            // Fallback to FOB
            $this->pageService->addGetPages($requests, self::BATCH_DATA_NAME_SOURCE, $this->pageParametersGroup);
        }*/
    }

    protected function setDcsData(): void {
        /*if (!$this->useEndpointDragAndDrop) {
            // Fallback: load pages from FOB batch data
            $this->pages = $this->getControllerData(self::BATCH_DATA_NAME_SOURCE);
        }*/

        // Apply relation resolver for products/categories enrichment
        $this->pages = PageRelationResolver::setData($this->pages);
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTemplateList = [];
        $widgetCss = '';
        $widgetJs = '';

        if ($this->pluginDcsEnabled && $this->dcsToken) {
            $widgetTypes = $this->collectPageWidgetTypes($this->pages?->getItems() ?? []);
            $widgetTemplateList = $this->widgetsService?->getWidgetTemplatesAsHtml($this->dcsToken) ?? [];
            if (!empty($widgetTypes)) {
                $widgetCss = $this->widgetsService?->getMergedWidgetCss($this->dcsToken, $widgetTypes) ?? '';
                $widgetJs = $this->widgetsService?->getMergedWidgetJs($this->dcsToken, $widgetTypes) ?? '';
            }
        }
        $this->setDataValue('widgetTemplateList', $widgetTemplateList);
        $this->setDataValue('widgetCss', $widgetCss);
        $this->setDataValue('widgetJs', $widgetJs);

        // Build dynamic CSS for custom widgets
        $css = PageRelationResolver::buildCustomPagesCss($this->pages?->getItems() ?? []);
        $this->setDataValue(self::DYNAMIC_MODULES_CSS, $css);
        $this->setDataValue('dcsToken', $this->dcsToken);
        $this->setDataValue('dcsPageId', $this->dcsPageId);
        $this->setDataValue('isDcsEnabled', $this->isDcsEnabled);
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
