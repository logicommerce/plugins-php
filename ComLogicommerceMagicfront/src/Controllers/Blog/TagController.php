<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Blog;

use FWK\Controllers\Blog\TagController as FWKBlogTagController;
use FWK\Core\Controllers\Controller;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\BlogControllerTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Blog\BlogPostParametersGroup;

/**
 * Plugin override for the blog tag route (RouteType::BLOG_TAG). Renders the
 * MagicFront widget blob via MagicfrontTrait and binds the tag's post list +
 * sidebar collections onto the widget Page DTOs.
 *
 * @see FWKBlogTagController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Blog
 */
class TagController extends FWKBlogTagController {
    use MagicfrontTrait;
    use BlogControllerTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $postsParams = new BlogPostParametersGroup();
        $postsParams->setTagId($this->getRoute()->getId());
        $this->addBlogBatch($requests, $postsParams);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->bindBlogPage(['blogTag' => $this->getControllerData(Controller::CONTROLLER_ITEM)]);
    }
}
