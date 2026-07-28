<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Blog;

use FWK\Controllers\Blog\PostController as FWKBlogPostController;
use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\BlogControllerTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the blog post route (RouteType::BLOG_POST). Renders the
 * MagicFront widget blob via MagicfrontTrait and binds the real route post +
 * sidebar collections + comments onto the widget Page DTOs so the blog widgets
 * read page.post / page.blogRecentPosts / page.blogCategories / page.blogTags /
 * page.blogComments.
 *
 * @see FWKBlogPostController
 */
class PostController extends FWKBlogPostController {
    use MagicfrontTrait;
    use BlogControllerTrait;

    private const COMMENTS = 'mffBlogComments';

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->addBlogBatch($requests);
        Loader::service(Services::BLOG)->addGetBlogPostComments($requests, self::COMMENTS, $this->getRoute()->getId());
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->bindBlogPage([
            'post'         => $this->getControllerData(Controller::CONTROLLER_ITEM),
            'blogComments' => $this->getControllerData(self::COMMENTS),
        ]);
    }
}
