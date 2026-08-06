<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use FWK\Controllers\HomeController as FWKHomeController;
use FWK\Core\Controllers\Controller;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the storefront home route (RouteType::HOME). The magicfront render source
 * (the singleton mff_HOME page) is resolved generically by {@see MagicfrontTrait::magicfrontPage()};
 * this controller only wires the trait's batch/data hooks into the FWK lifecycle. FWK's home never
 * populates `controllerItem`, so it is set to the mff_HOME page here (reusing the memoized resolve, no
 * extra query) — its content.header/footer per-page chrome overrides the generic mff_CHROME
 * (see TwigInitializer::pageChromeBlob), matching every other blob page.
 *
 * @see FWKHomeController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers
 */
class HomeController extends FWKHomeController {
    use MagicfrontTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->setMagicfrontBatchData($requests);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setDataValue(Controller::CONTROLLER_ITEM, $this->magicfrontPage());
        $this->setMagicfrontData();
    }
}
