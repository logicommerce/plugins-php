<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use SDK\Core\Enums\Resource;
use SDK\Dtos\Common\Route;
use SDK\Services\RouteService;

/**
 * Resolves an arbitrary storefront path into its {@see Route} — the same LC API call FWK's Router
 * uses for the current request ({@code Loader::service(ROUTE)->getRoute()}), but for a path we pass
 * in (a pageList subpage href) instead of the current request URL.
 *
 * SDK's {@see RouteService::getRoute()} only resolves the current request; its underlying
 * {@see RouteService::getParams()} already accepts a path but isn't exposed. Subclassing here reuses
 * that protected machinery from the plugin without touching the SDK.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class SubpageRouteResolver extends RouteService {

    public function __construct() {
    }

    public function routeByPath(string $path): ?Route {
        return $this->getResourceElement(Route::class, Resource::ROUTE, $this->getParams($path));
    }
}
