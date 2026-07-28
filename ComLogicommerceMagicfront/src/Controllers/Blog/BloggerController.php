<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Blog;

use FWK\Controllers\Blog\BloggerController as FWKBlogBloggerController;
use FWK\Core\Controllers\Controller;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\BlogControllerTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Blog\BlogPostParametersGroup;

/**
 * Plugin override for the blog author route (RouteType::BLOG_BLOGGER). Renders
 * the MagicFront widget blob via MagicfrontTrait and binds the blogger's post
 * list + sidebar collections onto the widget Page DTOs.
 *
 * @see FWKBlogBloggerController
 */
class BloggerController extends FWKBlogBloggerController {
    use MagicfrontTrait;
    use BlogControllerTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $postsParams = new BlogPostParametersGroup();
        $postsParams->setBloggerId($this->getRoute()->getId());
        $this->addBlogBatch($requests, $postsParams);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->bindBlogPage(['blogger' => $this->getControllerData(Controller::CONTROLLER_ITEM)]);
    }
}
