<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use FWK\Enums\RouteType;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Enums\AccountRedirectRoutes;
use Plugins\ComLogicommerceMagicfront\Enums\AvailablePagesValue;
use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    /** @var array per-request existence memo, keyed by pId */
    private array $specialPageExistsCache = [];

    protected array $properties = [];

    public function getProperties(): array {
        return $this->properties;
    }

    protected function setProperties(array $properties): void {
        $this->properties = $this->setArrayField($properties, PluginPropertiesProperty::class);
    }

    /** Layout/chrome routes — broad: any chrome toggle on → all routes. */
    public function getAvailablePages(): array {
        if (RenderMode::isPreviewMode() || $this->isHeaderOverlayEnabled() || $this->isFooterOverlayEnabled()) {
            return $this->allRouteTypes();
        }
        return $this->withAccountRedirects($this->routesFromAvailablepages());
    }

    /** Controller takeover routes — strict: only BO `availablepages` (chrome toggles don't broaden). */
    public function getControllerOverridePages(): array {
        if (RenderMode::isPreviewMode()) {
            return $this->allRouteTypes();
        }
        return $this->withAccountRedirects(array_values(array_filter(
            $this->routesFromAvailablepages(),
            fn(string $routeType): bool => $this->specialPagePublished($routeType)
        )));
    }

    /**
     * When ACCOUNT is being taken over (present in $routes — i.e. enabled and, for the strict gate,
     * published), the native account/user sub-routes are folded into /accounts/used, so append them
     * (see {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\AccountRedirectController}). If the
     * merchant disables account, ACCOUNT is absent and nothing is appended.
     *
     * @param string[] $routes
     * @return string[]
     */
    private function withAccountRedirects(array $routes): array {
        if (in_array(RouteType::ACCOUNT, $routes, true)) {
            $routes = array_merge($routes, AccountRedirectRoutes::types());
        }
        return array_values(array_unique($routes));
    }

    /**
     * A route with a singleton special page ({@see SpecialPagePId::forRouteType}) may take over ONLY
     * when that page exists; until it is published producción keeps serving the commerce's own
     * controller. Routes without one (e.g. PAGE / pageModules) are never gated — they carry their
     * own per-page blob and are handled downstream.
     */
    private function specialPagePublished(string $routeType): bool {
        $pId = SpecialPagePId::forRouteType($routeType);
        return $pId === null ? true : $this->pageExists($pId);
    }

    /**
     * Whether a MagicFront page with the given stable pId exists (is published). Per-request memoized,
     * shared by controller takeover AND chrome/panel injection so an unpublished special page keeps
     * producción on the commerce's own rendering. Public so {@see \Plugins\ComLogicommerceMagicfront\Core\Twig\TwigInitializer}
     * gates mff_CHROME / mff_PANELS through the same single query+cache.
     */
    public function pageExists(string $pId): bool {
        if (!array_key_exists($pId, $this->specialPageExistsCache)) {
            try {
                $params = new PageParametersGroup();
                $params->setPId($pId);
                $collection = Loader::service(Services::PAGE)->getPages($params);
                $this->specialPageExistsCache[$pId] = $collection instanceof ElementCollection
                    && ($collection->getItems()[0] ?? null) instanceof Page;
            } catch (\Throwable) {
                $this->specialPageExistsCache[$pId] = false;
            }
        }
        return $this->specialPageExistsCache[$pId];
    }

    /** @return string[] */
    private function routesFromAvailablepages(): array {
        return array_values(array_filter(array_map(
            static fn($v) => AvailablePagesValue::TO_ROUTE_TYPE[$v] ?? null,
            $this->getAvailablepagesValues()
        )));
    }

    /** @return string[] */
    private function allRouteTypes(): array {
        return array_values(array_filter(
            (new \ReflectionClass(\FWK\Enums\RouteType::class))->getConstants(),
            'is_string'
        ));
    }

    public function isHeaderOverlayEnabled(): bool {
        return $this->getBooleanProperty(PluginPropertiesPropertyNames::USEHEADER);
    }

    public function isFooterOverlayEnabled(): bool {
        return $this->getBooleanProperty(PluginPropertiesPropertyNames::USEFOOTER);
    }

    /** @return string[] */
    private function getAvailablepagesValues(): array {
        foreach ($this->properties as $property) {
            if ($property->getName() === PluginPropertiesPropertyNames::AVAILABLEPAGES) {
                return $property->getValue() ?? [];
            }
        }
        return [];
    }

    private function getBooleanProperty(string $name): bool {
        foreach ($this->properties as $property) {
            if ($property->getName() === $name) {
                return filter_var($property->getValue(), FILTER_VALIDATE_BOOLEAN);
            }
        }
        return false;
    }
}
