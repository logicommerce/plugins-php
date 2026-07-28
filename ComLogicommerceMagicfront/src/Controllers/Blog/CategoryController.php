<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Blog;

use FWK\Controllers\Blog\CategoryController as FWKBlogCategoryController;
use FWK\Core\Controllers\Controller;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\BlogControllerTrait;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\Blog\BlogPostParametersGroup;

/**
 * Plugin override for the blog category route (RouteType::BLOG_CATEGORY). Renders
 * the MagicFront widget blob via MagicfrontTrait and binds the real category + its
 * post list + sidebar collections onto the widget Page DTOs so the blog widgets
 * read page.blogCategory / page.category / page.blogPosts / page.blogRecentPosts /
 * page.blogCategories / page.blogTags.
 *
 * @see FWKBlogCategoryController
 */
class CategoryController extends FWKBlogCategoryController {
    use MagicfrontTrait;
    use BlogControllerTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $postsParams = new BlogPostParametersGroup();
        $postsParams->setCategoryId($this->getRoute()->getId());
        $this->addBlogBatch($requests, $postsParams);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $category = $this->getControllerData(Controller::CONTROLLER_ITEM);
        $this->bindBlogPage(['blogCategory' => $category, 'category' => $category]);
    }
}
