<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Page;

use FWK\Controllers\Page\ModuleController as FWKModuleController;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;

/**
 * This is the module page controller.
 * This class extends BasePageController (FWK\Core\Controllers\BasePageController), see this class.
 *
 * @see BaseHtmlController
 *
 * @package FWK\Controllers\Page
 */
class ModuleController extends FWKModuleController {
    use MagicfrontTrait;

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
        $this->pageParametersGroup->setParentId($this->getRoute()->getId());
        $this->pageParametersGroup->setLevels(self::MAX_PAGE_LEVELS);
        $this->magicfrontInit($route, $this->pageParametersGroup);
    }

    /**
     * This method is the one in charge of defining all the data batch requests that
     * are needed for the controller and adding them to the BatchRequests given by parameter.
     *
     * @param BatchRequests $request
     *            where the method will add the batch requests.
     * @return void
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
