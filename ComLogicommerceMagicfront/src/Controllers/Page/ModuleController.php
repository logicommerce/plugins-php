<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Page;

use FWK\Controllers\Page\ModuleController as FWKModuleController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * This is the module page controller.
 * This class extends FWK\Controllers\Page\ModuleController, see this class.
 *
 * @see FWKModuleController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Page
 */
class ModuleController extends FWKModuleController {
    use MagicfrontTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    /**
     * This method is the one in charge of defining all the data batch requests that
     * are needed for the controller and adding them to the BatchRequests given by parameter.
     *
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
     * @param array $additionalData Set additional data to the controller data.
     * @return void
     */
    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setMagicfrontData();
    }
}
