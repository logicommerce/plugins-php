<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\ViewHelpers\Util\Macro\Breadcrumb as BreadcrumbViewHelper;
use SDK\Dtos\Common\Route;

/**
 * Breadcrumb trail (`[{label, url}]`) for the generic breadcrumb widget, produced by the
 * SAME store logic: the FWK {@see BreadcrumbViewHelper} processes the route's ancestor chain —
 * resolving `{{wildcard}}` names to their language label (e.g. `{{home}}` → BREADCRUMB_HOME) and
 * applying showHome / showArea / show filtering exactly like the theme's util.breadcrumb macro.
 * Off a real route (editor / docker preview) getBreadcrumb() is empty → the widget shows its mock.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class BreadcrumbResolver {

    public static function build(Route $route): array {
        $params = (new BreadcrumbViewHelper([
            'data'     => $route->getBreadcrumb(),
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
}
