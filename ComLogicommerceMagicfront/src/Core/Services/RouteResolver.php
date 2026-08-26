<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use SDK\Core\Enums\Resource;
use SDK\Dtos\Common\Route;
use SDK\Services\RouteService;

/**
 * Plugin-local route resolver.
 *
 * Subclasses SDK's RouteService only to reach its protected getParams()/getResourceElement()
 * with a custom path — the base class hardcodes the current request URL, which is not what
 * the widget handler needs when it resolves the page from HTTP_REFERER.
 *
 * @see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler::resolveBlobPId()
 */
class RouteResolver extends RouteService {

    /**
     * Resolves the LC Route DTO for an arbitrary storefront URL/path (e.g. a Referer) via
     * the same public route API the storefront uses for its own request.
     */
    public static function forPath(string $url): ?Route {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $resolver = new self();
        $route = $resolver->getResourceElement(Route::class, Resource::ROUTE, $resolver->getParams($path));
        return $route instanceof Route ? $route : null;
    }
}
