<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers;

use FWK\Controllers\HomeController as FWKHomeController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * This is the HomeController controller class, it is an extension of the framework class \FWK\Controllers\HomeController, see this class.
 *
 * @see FWKHomeController
 *
 * @package SITE\Controllers
 */
class HomeController extends FWKHomeController {
    use MagicfrontTrait;

    public const PAGE_MODULES_POSITION = 999;

    public const MAX_PAGE_LEVELS = 4;

    public ?PageParametersGroup $pageParametersGroup = null;

    /**
     * Constructor method.
     *
     * @param Route $route
     */
    public function __construct(Route $route) {
        parent::__construct($route);
        $this->pageParametersGroup = new PageParametersGroup();
        $this->pageParametersGroup->setPosition(self::PAGE_MODULES_POSITION);
        $this->pageParametersGroup->setLevels(self::MAX_PAGE_LEVELS);
        $this->magicfrontInit($route, $this->pageParametersGroup);
    }

    /**
     * This private method sends the batch requests of the controller to the SDK to obtain the data and returns the batch result
     *
     * @return array with the result of the batch request
     */
    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->setMagicfrontBatchData($requests);
    }

    /**
     * This method runs after the batch requests (defined in the setBatchData methods) are resolved,
     * so here you can work with the response of the batch requests and calculate and set more needed data.
     *
     * @param array $additionalData
     *              Set additiona data to the controller data
     * 
     * @return void
     */
    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setMagicfrontData();
    }
}
