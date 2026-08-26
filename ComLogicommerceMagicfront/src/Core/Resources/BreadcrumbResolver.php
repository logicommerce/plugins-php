<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Loader;
use FWK\Enums\LanguageLabels;
use FWK\Enums\Services;
use FWK\ViewHelpers\Util\Macro\Breadcrumb as BreadcrumbViewHelper;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Dtos\Common\Route;

/**
 * Breadcrumb trail (`[{label, url}]`) for the generic breadcrumb widget, produced by the
 * SAME store logic: the FWK {@see BreadcrumbViewHelper} processes the route's ancestor chain —
 * resolving `{{wildcard}}` names to their language label (e.g. `{{home}}` → BREADCRUMB_HOME) and
 * applying showHome / showArea / show filtering exactly like the theme's util.breadcrumb macro.
 * Off a real route (editor / docker preview) getBreadcrumb() is empty → the widget shows its mock.
 *
 * Subpages have no route of their own and their parent link is a MagicFront concept, not an LC page
 * hierarchy — it lives in the published blob as `content.parentId` (NOT SDK `Page::getParentPageId()`,
 * which is 0 for these). When the rendered page's blob carries a `content.parentId`, the ancestor
 * pages are walked (each loaded by id, then re-reading ITS blob's `content.parentId`) and their
 * crumbs inserted before the current page — supporting arbitrarily deep subpage nesting.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class BreadcrumbResolver {

    private const MAX_DEPTH = 10;

    public static function build(Route $route, ?Page $page = null): array {
        $trail   = self::routeTrail($route);
        $parents = $page !== null ? self::subpageParents($page) : [];
        if ($parents === []) {
            return $trail;
        }
        $current = array_pop($trail);
        $trail   = array_merge($trail, $parents);
        if ($current !== null) {
            $trail[] = $current;
        }
        return $trail;
    }

    /**
     * @return array
     */
    private static function routeTrail(Route $route): array {
        $params = (new BreadcrumbViewHelper([
            'data'     => self::resolvableCrumbs($route->getBreadcrumb()),
            'showHome' => true,
            'showArea' => false,
        ]))->getViewParameters();
        $trail = [];
        foreach ($params['data'] as $crumb) {
            if (!$crumb->getShow()) {
                continue;
            }
            $trail[] = [
                'label' => $crumb->getName(),
                'url'   => $crumb->getUrlSeo(),
            ];
        }
        return $trail;
    }

    /**
     * The page's ancestor crumbs (root-most first, direct parent last), walked via the blob's
     * `content.parentId` at each level.
     *
     * @return array
     */
    private static function subpageParents(Page $page): array {
        $chain    = [];
        $parentId = self::blobParentId($page);
        $depth    = 0;
        while ($parentId > 0 && $depth++ < self::MAX_DEPTH) {
            $parent = Loader::service(Services::PAGE)->getPageById($parentId);
            if (!$parent instanceof Page) {
                break;
            }
            $lang    = $parent->getLanguage();
            $chain[] = [
                'label' => $lang !== null ? $lang->getName() : '',
                'url'   => $lang !== null ? $lang->getUrlSeo() : '',
            ];
            $parentId = self::blobParentId($parent);
        }
        return array_reverse($chain);
    }

    /** The MagicFront parent-page id from the page's published blob (`content.parentId`), or 0. */
    private static function blobParentId(Page $page): int {
        $blob = json_decode((string) ($page->getLanguage()?->getPageContent() ?? ''), true);
        return is_array($blob) ? (int) ($blob['content']['parentId'] ?? 0) : 0;
    }

    /**
     * Drop crumbs whose `{{wildcard}}` name has no matching `FWK\Enums\LanguageLabels::BREADCRUMB_<WILDCARD>`
     * constant. The FWK breadcrumb view-helper resolves those wildcards by reflecting that constant WITHOUT
     * an existence check, so an unmapped route type (e.g. ACCOUNT → BREADCRUMB_ACCOUNT, which is missing)
     * would throw and fatal (502) inside emitMagicfrontData. Pre-filtering avoids the throw entirely while
     * leaving every resolvable crumb untouched — no broad exception swallowing.
     *
     * @param  mixed[] $crumbs
     * @return mixed[]
     */
    private static function resolvableCrumbs(array $crumbs): array {
        return array_values(array_filter($crumbs, static function ($crumb): bool {
            $name = is_object($crumb) && method_exists($crumb, 'getName') ? (string) $crumb->getName() : '';
            if (!preg_match('/^{{(.*)}}$/', $name, $matches)) {
                return true;
            }
            return defined(LanguageLabels::class . '::BREADCRUMB_' . strtoupper($matches[1]));
        }));
    }
}
