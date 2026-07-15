<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use FWK\Controllers\HomeController as FWKHomeController;
use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * Plugin override for the storefront home route (RouteType::HOME). FWK's
 * HomeController extends BaseHtmlController and never populates `controllerItem`,
 * so MagicfrontTrait::loadStorefrontData has nothing to read. We resolve the
 * backing LC page by its stable pId ('mff_HOME') — set once at plugin install —
 * and promote it into `controllerItem` before setMagicfrontData runs.
 *
 * @see FWKHomeController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers
 */
class HomeController extends FWKHomeController {
    use MagicfrontTrait;

    private const HOME_PAGE_PID = 'mff_HOME';

    private const HOME_LOOKUP_KEY = 'mffHomeLookup';

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->addHomePageLookup($requests);
        $this->setMagicfrontBatchData($requests);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->promoteHomePageToControllerItem();
        $this->setMagicfrontData();
    }

    private function addHomePageLookup(BatchRequests $requests): void {
        $params = new PageParametersGroup();
        $params->setPId(self::HOME_PAGE_PID);
        Loader::service(Services::PAGE)->addGetPages($requests, self::HOME_LOOKUP_KEY, $params);
    }

    private function promoteHomePageToControllerItem(): void {
        $collection = $this->getControllerData(self::HOME_LOOKUP_KEY);
        if ($collection instanceof ElementCollection) {
            $first = $collection->getItems()[0] ?? null;
            if ($first instanceof Page) {
                $this->setDataValue(Controller::CONTROLLER_ITEM, $first);
            }
        }
        $this->deleteControllerData(self::HOME_LOOKUP_KEY);
    }
}
