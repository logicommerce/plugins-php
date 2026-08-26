<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use FWK\Core\Resources\Session;
use SDK\Dtos\Common\Route;

/**
 * Immutable facade a {@see WidgetDataProvider} reads from, decoupling providers from the
 * route controller. Carries the current route, the storefront session, the set of widget
 * TYPES present on the page (so a provider runs only when its widgets are placed — the basis
 * for adding e.g. account widgets on the home page), a resolver for controllerData / batch
 * results, and the optional route ENTITY (the routed product/category/…) for entity-bound
 * providers — which fall back to per-widget settings when the entity is absent (off-route).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class ProviderContext {

    /** @param \Closure(string): mixed $dataResolver reads controllerData / batch results by key */
    public function __construct(
        private readonly ?Route $route,
        private readonly ?Session $session,
        private readonly array $presentTypes,
        private readonly \Closure $dataResolver,
        private readonly mixed $routeEntity = null,
        private readonly bool $canvasMode = false,
    ) {
    }

    public function route(): ?Route {
        return $this->route;
    }

    /** THE editor canvas ({@see \Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode::isCanvasMode()}):
     *  widgets render their mff_isCanvasMode() mock there, so providers must NOT fetch real
     *  account/catalog data (no getOrders/… API calls in the editor). The standalone preview tab is a
     *  real storefront render and DOES fetch. */
    public function canvasMode(): bool {
        return $this->canvasMode;
    }

    public function session(): ?Session {
        return $this->session;
    }

    /** @return string[] widget types present on the page */
    public function presentTypes(): array {
        return $this->presentTypes;
    }

    /** @param string[] $types */
    public function hasAnyType(array $types): bool {
        return $types !== [] && array_intersect($types, $this->presentTypes) !== [];
    }

    public function getData(string $key): mixed {
        return ($this->dataResolver)($key);
    }

    /** The routed entity (product/category/…) for entity-bound providers, or null off-route. */
    public function routeEntity(): mixed {
        return $this->routeEntity;
    }
}
