<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers;

use FWK\Core\Controllers\BaseHtmlController;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\RoutePaths;
use FWK\Enums\RouteType;
use Plugins\ComLogicommerceMagicfront\Enums\AccountRedirectRoutes;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Base for the account-area takeover: when the MagicFront account page (`mff_USER_AREA`) is published,
 * the plugin claims every native account/user PAGE route it has a panel for and folds it into the
 * single MFF account page. Each such route 302-redirects to `/accounts/used?mfPanel=<section>` (the
 * matching {@see AccountRedirectRoutes::PANEL} value; empty = landing, no panel).
 *
 * The concrete per-route subclasses (`Controllers/User/*`, `Controllers/Account/*`) are empty — they
 * exist only so {@see \FWK\Core\Controllers\ControllersFactory} resolves the class in the plugin
 * namespace for that route type; all behaviour lives here. The redirect runs in the constructor so no
 * batch/render work happens: {@see Response::redirect()} sends the Location header and ends the request.
 * The gate ({@see \Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties::getControllerOverridePages()})
 * only routes here when `mff_USER_AREA` is published, so producción keeps the native account pages
 * until the MagicFront account page exists.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers
 */
abstract class AccountRedirectController extends BaseHtmlController {

    public function __construct(Route $route) {
        parent::__construct($route);
        $panel = AccountRedirectRoutes::PANEL[$route->getType()] ?? null;
        $url = RoutePaths::getPath(RouteType::ACCOUNT);
        if ($panel !== null && $panel !== '') {
            $url .= '?mfPanel=' . rawurlencode($panel);
        }
        Response::redirect($url);
    }

    // Never executed — the constructor redirects and ends the request before any of these run.
    // Declared only to satisfy the abstract Controller contract so the concrete subclasses are valid.
    protected function setControllerBaseBatchData(BatchRequests $requests): void {
    }

    protected function setBatchData(BatchRequests $request): void {
    }

    protected function setData(array $additionalData = []): void {
    }
}
