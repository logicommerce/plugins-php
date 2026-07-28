<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\RoutePaths;
use FWK\Enums\LanguageLabels;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Services\BlogPageBinder;
use SDK\Core\Resources\BatchRequests;
use SDK\Enums\BlogPostSort;
use SDK\Enums\RouteType;
use SDK\Services\Parameters\Groups\Blog\BlogPostParametersGroup;

/**
 * Blog-specific controller helpers shared by the blog route overrides
 * (home / category / post / tag / blogger). Kept apart from {@see MagicfrontTrait}
 * (the general magicfront mixin): a blog controller composes both. Relies on
 * MagicfrontTrait for setMagicfrontBatchData() / setMagicfrontData() and the
 * $pages state, and on the FWK Controller base for getControllerData() /
 * getRoute() / getLanguageSheet().
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait BlogControllerTrait {

    private const BLOG_POSTS = 'mffBlogPosts';

    private const BLOG_RECENT_POSTS = 'mffBlogRecentPosts';

    private const BLOG_CATEGORIES = 'mffBlogCategories';

    // 10, not 5: the blogRecentPosts widget exposes a merchant-editable maxItems (default 5) —
    // the server sends up to this many and the widget slices down to its setting.
    private const BLOG_RECENT_LIMIT = 10;

    /**
     * Adds the batch requests shared by every blog listing route: the sidebar
     * recent-posts and categories collections plus the magicfront widget batch.
     * When $postsParams is given, the main post list is fetched too (home =
     * unfiltered, category / tag / blogger = filtered). Call from a blog
     * controller's setBatchData() after parent::setBatchData().
     */
    protected function addBlogBatch(BatchRequests $requests, ?BlogPostParametersGroup $postsParams = null): void {
        $blogService = Loader::service(Services::BLOG);
        if ($postsParams !== null) {
            $blogService->addGetBlogPosts($requests, self::BLOG_POSTS, $postsParams);
        }
        $recent = new BlogPostParametersGroup();
        $recent->setPerPage(self::BLOG_RECENT_LIMIT);
        $recent->setSort(BlogPostSort::PUBLICATIONDATE . '.' . BlogPostSort::SORT_DIRECTION_DESC);
        $blogService->addGetBlogPosts($requests, self::BLOG_RECENT_POSTS, $recent);
        $blogService->addGetBlogCategories($requests, self::BLOG_CATEGORIES);
        $this->setMagicfrontBatchData($requests);
    }

    /**
     * Resolves the magicfront widget data and binds the blog data shared by every
     * blog route (post list + sidebar collections + tags + breadcrumb) onto the
     * widget Page DTOs, merged with the per-route $extra. The breadcrumb leaf is the
     * route's CONTROLLER_ITEM (post / category / tag / blogger; blog home has no
     * nameable item so no leaf crumb is added). Call from a blog controller's
     * setData() after parent::setData().
     *
     * @param array<string, mixed> $extra Route-specific bindings (e.g. post / blogCategory); keys win over the shared set.
     */
    protected function bindBlogPage(array $extra = []): void {
        $this->setMagicfrontData();
        $session = $this->getSession();
        $this->bindBlogData($extra + [
            'blogPosts'       => $this->getControllerData(self::BLOG_POSTS),
            'blogSettings'    => Loader::service(Services::SETTINGS)->getBlogSettings(),
            'userLogged'      => $session !== null && $session->isLogged(),
            'blogRecentPosts' => $this->getControllerData(self::BLOG_RECENT_POSTS),
            'blogCategories'  => $this->getControllerData(self::BLOG_CATEGORIES),
            'blogTags'        => Loader::service(Services::BLOG)->getAllTags(),
            'breadcrumb'      => $this->mffBlogCrumbs($this->getControllerData(Controller::CONTROLLER_ITEM)),
        ]);
    }

    /**
     * Builds the blog breadcrumb crumbs (home > blog > {item name}) consumed by the
     * blogBreadcrumb widget as page.breadcrumb. Labels come from the language sheet
     * and urls from RoutePaths; the trailing crumbs are resolved from API data. Each
     * crumb is ['label', 'url'] — the field names the breadcrumb widget reads (crumb.label).
     *
     * @return array<int, array{label: string, url: string}>
     */
    protected function mffBlogCrumbs(mixed $leafItem): array {
        $sheet = $this->getLanguageSheet();
        $crumbs = [
            ['label' => (string) $sheet[LanguageLabels::BREADCRUMB_HOME], 'url' => RoutePaths::getPath(RouteType::HOME)],
            ['label' => (string) $sheet[LanguageLabels::BREADCRUMB_BLOGHOME], 'url' => RoutePaths::getPath(RouteType::BLOG_HOME)],
        ];
        if (is_object($leafItem) && method_exists($leafItem, 'getMainCategoryName')) {
            $catName = (string) $leafItem->getMainCategoryName();
            if ($catName !== '') {
                $crumbs[] = ['label' => $catName, 'url' => ''];
            }
        }
        if (is_object($leafItem) && method_exists($leafItem, 'getLanguage') && $leafItem->getLanguage() !== null) {
            $lang = $leafItem->getLanguage();
            $name = match (true) {
                method_exists($lang, 'getName')  => (string) $lang->getName(),
                method_exists($lang, 'getValue') => (string) $lang->getValue(),
                default                          => '',
            };
            if ($name !== '') {
                $url = method_exists($lang, 'getUrlSeo') ? (string) $lang->getUrlSeo() : '';
                $crumbs[] = ['label' => $name, 'url' => $url];
            }
        }
        return $crumbs;
    }

    /**
     * Binds route-resolved blog data onto every widget Page DTO so blog widgets read
     * it as page.post / page.blogPosts / page.breadcrumb / ... Delegates the tree walk
     * to {@see BlogPageBinder}.
     *
     * @param array<string, mixed> $data
     */
    protected function bindBlogData(array $data): void {
        (new BlogPageBinder($data))->applyTo($this->pages);
    }
}
