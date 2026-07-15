<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Page;

use FWK\Controllers\Page\PageController as FWKPageController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the default LogiCommerce page controller (RouteType::PAGE,
 * pageType=DEFAULT). MagicFront publishes its pages as DEFAULT-type LC pages —
 * see PublishClientImpl in dcsapi — so the storefront route resolves here, not
 * to the MODULE controller.
 *
 * @see FWKPageController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Page
 */
class PageController extends FWKPageController {
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
        $this->setMagicfrontData();
    }
}
