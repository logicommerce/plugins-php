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
        private readonly ?array $sharedNeeds = null,
        /** The widget templates this render will paint, keyed by type. Values are raw templateHtml on
         *  the AJAX path and WidgetTemplate objects on the full-page path (fwk hands the DTOs and the
         *  HTML is pulled with getTemplateHtml()); {@see self::templatesReference()} accepts both, and
         *  assuming strings made it silently never match. Lets a provider decide by what the templates
         *  actually READ instead of by a hardcoded widget-type list. */
        private readonly array $templates = [],
    ) {
    }

    /**
     * Whether a template of this render reads the costly `shared.$key` ({@see SharedNeeds}, #4251). Without the
     * templates (null) every key is needed: a caller that cannot say keeps the full container.
     */
    public function needsShared(string $key): bool {
        return $this->sharedNeeds === null || in_array($key, $this->sharedNeeds, true);
    }

    /**
     * Whether any template in this render references `$needle` (e.g. `page.customTags.account`).
     *
     * A provider keyed on this is self-describing: a widget declares the dependency by reading the
     * key in its own Twig, and the data follows — no provider-side list of widget types to keep in
     * sync as widgets are added, renamed or start/stop using a field.
     */
    public function templatesReference(string $needle): bool {
        foreach ($this->templates as $template) {
            $html = is_string($template) ? $template
                : (is_object($template) && method_exists($template, 'getTemplateHtml') ? $template->getTemplateHtml() : null);
            if (is_string($html) && str_contains($html, $needle)) {
                return true;
            }
        }
        return false;
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
