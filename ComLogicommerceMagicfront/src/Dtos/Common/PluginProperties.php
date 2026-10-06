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
use SDK\Core\Registry;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class PluginProperties extends CorePluginProperties {
    use ElementTrait;

    /**
     * Per-REQUEST memo of the special pages, keyed by pId: the page, or null when it is not published. Static, not
     * per object: the FWK builds more than one PluginProperties in a request, and each one would ask again.
     *
     * @var array
     */
    private static array $specialPages = [];

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
            fn(string $routeType): bool => !$this->decidesThisRequest($routeType) || $this->specialPagePublished($routeType)
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
     * Whether `$routeType` decides anything in THIS request. The FWK only asks whether the CURRENT route is in
     * {@see getControllerOverridePages()}, yet every type the merchant enabled used to be checked against the API on
     * every page view — one call per type, each bringing the whole page: 3-5 s per page on nightly. A type that
     * decides nothing here is listed by the merchant's setting alone. What decides: the current route, and the
     * account page when the current route is one of the account sub-routes folded into it. When the current route
     * is not known yet, every type decides, as before.
     */
    private function decidesThisRequest(string $routeType): bool {
        try {
            $current = Registry::exist(Registry::PAGE_TYPE) ? (string) Registry::get(Registry::PAGE_TYPE) : '';
        } catch (\Throwable) {
            $current = '';
        }
        if ($current === '' || $current === $routeType) {
            return true;
        }
        return $routeType === RouteType::ACCOUNT && in_array($current, AccountRedirectRoutes::types(), true);
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
     * gates mff_CHROME / mff_PANELS through the same single query+cache. Always asked in THIS request, never
     * remembered from an earlier one: a page unpublished a second ago is already gone here, so a route never takes
     * over to paint a page that is not there.
     */
    public function pageExists(string $pId): bool {
        return self::loadSpecialPage($pId) !== null;
    }

    /**
     * The published special page with this pId, fetched once per request: whoever asks first (the existence check,
     * the route painting it, the chrome) brings it, and the others reuse it. Each one used to be fetched twice per
     * page view. A failed call counts as not published for this request, as before.
     */
    public static function loadSpecialPage(string $pId): ?Page {
        if (!array_key_exists($pId, self::$specialPages)) {
            $page = null;
            try {
                $params = new PageParametersGroup();
                $params->setPId($pId);
                $collection = Loader::service(Services::PAGE)->getPages($params);
                $first = $collection instanceof ElementCollection ? ($collection->getItems()[0] ?? null) : null;
                $page = $first instanceof Page ? $first : null;
            } catch (\Throwable) {
                $page = null;
            }
            self::$specialPages[$pId] = $page;
        }
        return self::$specialPages[$pId];
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
