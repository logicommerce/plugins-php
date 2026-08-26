<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Blog;

use FWK\Controllers\Blog\HomeController as FWKBlogHomeController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\BlogControllerTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Blog\BlogPostParametersGroup;

/**
 * Plugin override for the blog home route (RouteType::BLOG_HOME, /blog). Renders
 * the MagicFront widget blob via MagicfrontTrait and binds the full post list +
 * sidebar collections onto the widget Page DTOs.
 *
 * @see FWKBlogHomeController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Blog
 */
class HomeController extends FWKBlogHomeController {
    use MagicfrontTrait;
    use BlogControllerTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->addBlogBatch($requests, new BlogPostParametersGroup());
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->bindBlogPage();
    }
}
