<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use FWK\Enums\RouteType;
use FWK\Enums\RouteTypes\InternalAccount;
use FWK\Enums\RouteTypes\InternalCheckout;
use FWK\Enums\RouteTypes\InternalUser;
use Plugins\ComLogicommerceMagicfront\Core\Checkout\CheckoutRoute;
use Plugins\ComLogicommerceMagicfront\Core\Resources\OrderDetailUrl;
use Plugins\ComLogicommerceMagicfront\Core\Theme\CaptchaRule;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Enums\AccountRedirectRoutes;
use Plugins\ComLogicommerceMagicfront\Enums\AvailablePagesValue;
use Plugins\ComLogicommerceMagicfront\Enums\PluginPropertiesPropertyNames;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Core\Dtos\PluginProperties as CorePluginProperties;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Traits\ElementTrait;
use SDK\Core\Registry;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
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

    /** Name of each special page in a batch call ({@see self::loadSpecialPages()}), followed by its pId. */
    private const SPECIAL_PAGE_BATCH_KEY = 'mffSpecialPage_';

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
            return self::withoutOrderPagesTheStoreServes($this->allRouteTypes());
        }
        return self::withoutOrderPagesTheStoreServes($this->withAccountRedirects(array_values(array_filter(
            $this->routesFromAvailablepages(),
            fn(string $routeType): bool => !$this->decidesThisRequest($routeType) || $this->specialPagePublished($routeType)
        ))));
    }

    /**
     * The address of one order stays LogiCommerce's when LogiCommerce itself asks for it: its «view order» popup (AJAX)
     * and a guest's order (`?token=`). Taken over, both were redirected to the account page: the popup filled with the
     * whole page and the guest landed on the login (#4508, {@see OrderDetailUrl::keepsStorePage()}).
     *
     * @param string[] $routes
     * @return string[]
     */
    private static function withoutOrderPagesTheStoreServes(array $routes): array {
        // Built only when the address has a token: the FWK's filter needs the FWK, which the plugins CI does not have.
        $guestToken = RequestQuery::has(OrderDetailUrl::GUEST_TOKEN)
            ? RequestQuery::value(OrderDetailUrl::GUEST_TOKEN, FilterInputFactory::getTokenParameter()[Parameters::TOKEN])
            : null;
        if (!OrderDetailUrl::keepsStorePage($guestToken)) {
            return $routes;
        }
        return array_values(array_diff($routes, AccountRedirectRoutes::ORDER));
    }

    /**
     * Theme configuration takeover routes, read by FWK PluginOverrideTrait::getActiveTcModifiers() for
     * {@see \Plugins\ComLogicommerceMagicfront\Core\Theme\TcModifier}: the submits of the forms MagicFront
     * renders on the pages the merchant enabled in BO, and the checkout routes FWK steers by the one step
     * checkout setting. Kept apart from the layout and controller lists so these internal routes never get the
     * chrome/Twig initializer; each rule reads the PUBLISHED page itself, so while the page is unpublished it
     * changes nothing. Plus the submits of every form LogiCommerce protects with its captcha, where the MagicFront
     * widget that sent the form decides it ({@see CaptchaRule}): those forms can sit on any page, so always.
     *
     * @return string[]
     */
    public function getTcOverridePages(): array {
        $routes = $this->routesFromAvailablepages();
        return array_values(array_unique(array_merge(
            in_array(RouteType::CHECKOUT, $routes, true) ? [InternalCheckout::ADD_CUSTOMER, InternalCheckout::OSC_RECALCULATE, InternalCheckout::NEXT_STEP, InternalUser::SET_ADDRESS_BOOK, ...CheckoutRoute::oneStepRoutes()] : [],
            in_array(RouteType::ACCOUNT, $routes, true) ? [InternalUser::ADD_USER, InternalAccount::UPDATE_ACCOUNT, InternalAccount::UPDATE_REGISTERED_USER] : [],
            CaptchaRule::routes()
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
     * gates mff_HEADER / mff_FOOTER and the legacy mff_CHROME / mff_PANELS through the same memo, filled for the four
     * in one batch call ({@see self::loadSpecialPages()}). Always asked in THIS request, never
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
                $page = self::firstPage(Loader::service(Services::PAGE)->getPages(self::specialPageParams($pId)));
            } catch (\Throwable) {
                $page = null;
            }
            self::$specialPages[$pId] = $page;
        }
        return self::$specialPages[$pId];
    }

    /**
     * Brings into the same per-request memo, in ONE batch call, those of `$pIds` this request has not asked for yet:
     * the chrome pages (the header, the footer and their legacy pages) are read together, so a store keeps one call
     * however many of them it has. Nothing is asked when every one is already known. A failed call, or a failed
     * answer, counts as not published for this request, as in {@see self::loadSpecialPage()}.
     *
     * @param string[] $pIds
     */
    public static function loadSpecialPages(array $pIds): void {
        $missing = array_values(array_unique(array_filter($pIds,
            static fn(string $pId): bool => !array_key_exists($pId, self::$specialPages))));
        if ($missing === []) {
            return;
        }
        try {
            $requests = new BatchRequests();
            foreach ($missing as $pId) {
                Loader::service(Services::PAGE)->addGetPages($requests, self::SPECIAL_PAGE_BATCH_KEY . $pId, self::specialPageParams($pId));
            }
            $answers = BatchService::getInstance()->send($requests);
        } catch (\Throwable) {
            $answers = [];
        }
        foreach ($missing as $pId) {
            self::$specialPages[$pId] = self::firstPage($answers[self::SPECIAL_PAGE_BATCH_KEY . $pId] ?? null);
        }
    }

    private static function specialPageParams(string $pId): PageParametersGroup {
        $params = new PageParametersGroup();
        $params->setPId($pId);
        return $params;
    }

    /** The page an answer of the page service brings, or null when it brings none (not published, or an error). */
    private static function firstPage(mixed $collection): ?Page {
        $first = $collection instanceof ElementCollection ? ($collection->getItems()[0] ?? null) : null;
        return $first instanceof Page ? $first : null;
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
