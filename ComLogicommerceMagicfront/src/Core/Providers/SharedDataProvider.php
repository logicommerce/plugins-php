<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use FWK\Core\Controllers\Traits\AddDefaultCountryAndLocationsTrait;
use FWK\Core\Resources\DateTimeFormatter;
use FWK\Core\Resources\Language;
use FWK\Core\Resources\RoutePaths;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Utils;
use FWK\Services\LmsService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BasketWidgetData;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WeekStart;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetLocator;
use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\MagicfrontProductFunctions;
use SDK\Application;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Basket\Basket;
use SDK\Enums\PostalCodeType;

/**
 * Provides the platform-neutral `shared.*` container consumed by ANY widget (not just account) — the
 * FWK `session`/`settings` globals mirrored whole (`shared.session`, `shared.settings`) plus derived
 * env data (`countries`, `countryNames`, `locationData`, `companyRoles`, `customTags`); base `licenses`
 * come from {@see \Plugins\ComLogicommerceMagicfront\Dtos\Common\BaseShared}. A widget reads uniform `shared.*` keys regardless of
 * platform — porting = re-fill these in the plugin, widget JSON untouched.
 *
 * `shared.*` is UNIVERSAL — {@see self::appliesTo()} always returns true, so it is built on every
 * render and reaches EVERY widget (not just account/location widgets; e.g. an `image` widget can read
 * `shared.session`/`shared.settings`/`shared.licenses`). {@see self::attach()} still fetches per-widget
 * account business data via {@see DataWidgetRegistry}, but that step self-gates (no account / no
 * fetching widget present → no fetch).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class SharedDataProvider implements WidgetDataProvider {

    use AddDefaultCountryAndLocationsTrait;

    /** @var array<string,string>|null */
    private ?array $countryNameMap = null;

    /** @var array|null */
    private ?array $countrySettingsItems = null;

    /**
     * The widget types whose business data this provider fetches (into `page.*`) via {@see DataWidgetRegistry}: the
     * data widgets and the pieces that read their data.
     */
    public function handledTypes(): array {
        return array_merge(DataWidgetRegistry::types(), DataWidgetRegistry::pieceTypes());
    }

    /** Always applies: `shared.*` is universal env data for ANY widget, built on every render. */
    public function appliesTo(ProviderContext $ctx): bool {
        return true;
    }

    public function addBatch(BatchRequests $requests, ProviderContext $ctx): void {
    }

    public function attach(?ElementCollection $pages, ProviderContext $ctx): void {
        if ($pages === null) {
            return;
        }
        // The editor CANVAS renders every panel from its mff_isCanvasMode() MOCK, so the real account
        // data is never read there — skip all fetches to keep zero account-API calls (getOrders, …) in
        // the editor. Storefront AND the standalone preview tab fall through and fetch for real.
        if ($ctx->canvasMode()) {
            return;
        }
        $logged = !empty($ctx->session()?->getBasket()?->getAccount()?->getId());
        foreach (DataWidgetRegistry::types() as $type) {
            if ($ctx->hasAnyType([$type])) {
                if ((!$logged && DataWidgetRegistry::requiresAccount($type)) || DataWidgetRegistry::isDeferred($type)) {
                    continue;
                }
                DataWidgetRegistry::fetchInto($pages, $type, WidgetLocator::findByType($pages, $type));
                continue;
            }
            if (!$logged) {
                continue;
            }
            // No data widget in the panel, but a piece that reads its data (the account Summary's, E22): the piece
            // asks for it. With the widget there too, the widget's fetch above already serves the piece.
            foreach (DataWidgetRegistry::piecesReading($type) as $piece) {
                if ($ctx->hasAnyType([$piece])) {
                    DataWidgetRegistry::fetchInto($pages, $piece, WidgetLocator::findByType($pages, $piece));
                    break;
                }
            }
        }
    }

    /**
     * @return array `shared.*` extras (session/settings/countries/countryNames/locationData/basket/companyRoles/customTags/paths/calendar).
     *         The countries, the locations and the basket only when a template of the render reads them ({@see SharedNeeds}, #4251):
     *         they cost API calls (or writing out every row) on every page and only the blocks that ask for them use them.
     */
    public function sharedData(ProviderContext $ctx): array {
        return [
            'session'      => $this->buildSessionShared(),
            'settings'     => $this->buildSettingsShared(),
            'companyRoles' => $this->serialize($ctx->getData('companyRoles')),
            'customTags'   => $this->serialize($ctx->getData('customTags')),
            'paths'        => $this->buildPaths(),
            'calendar'     => self::buildCalendar(),
        ] + $this->onDemandShared(array_filter(SharedNeeds::ON_DEMAND, [$ctx, 'needsShared']));
    }

    /**
     * The costly `shared.*` keys asked for ({@see SharedNeeds::ON_DEMAND}); any other key is ignored. Also what the
     * header, footer and panels get on top of the page's container when one of their blocks reads a key the page
     * did not need.
     *
     * @param string[] $keys
     * @return array
     */
    public function onDemandShared(array $keys): array {
        $shared = [];
        foreach (array_intersect(SharedNeeds::ON_DEMAND, $keys) as $key) {
            $shared[$key] = match ($key) {
                'countries'    => $this->buildCountries(),
                'countryNames' => $this->buildCountryNames(),
                'locationData' => $this->buildLocationData(),
                'basket'           => self::sessionBasketShared(),
                'categoryDropdown' => self::categoryDropdownShared(),
            };
        }
        return $shared;
    }

    /** Memo of the sample basket for this request: one call however many pieces paint. */
    private static ?array $sampleBasket = null;

    /** MagicFront's sample basket in the language being edited, or null when the backend does not answer one. */
    private static function sampleBasket(): ?array {
        if (self::$sampleBasket !== null) {
            return self::$sampleBasket;
        }
        try {
            $language = MagicfrontUtils::contentLanguage(Language::getInstance()->getLanguage());
            $sample = WidgetsService::getInstance()->getSample('basket', $language);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($sample) || !isset($sample['items']) || !is_array($sample['items'])) {
            return null;
        }
        $sample['mffSample'] = true;
        return self::$sampleBasket = $sample;
    }

    /** Memo of the sample category of the header's dropdown for this request: one call however many pieces paint. */
    private static ?array $sampleCategoryDropdown = null;

    /**
     * `shared.categoryDropdown`: in the canvas, MagicFront's sample category (`GET /samples/category-dropdown`, the
     * furniture of the sample store, in the language being edited), with the shape of an entry of mff_getCategories():
     * the dropdown's pieces paint it FIRST, ahead of the store's own categories, so the merchant designs the dropdown on
     * a full branch (picture, description, two levels) whatever the catalogue has, and hovering the real bar still opens
     * the real category (02-11-2026). Outside the canvas, or when the backend does not answer, empty: nothing changes.
     */
    private static function categoryDropdownShared(): array {
        if (!RenderMode::isCanvasMode()) {
            return [];
        }
        if (self::$sampleCategoryDropdown !== null) {
            return self::$sampleCategoryDropdown;
        }
        try {
            $language = MagicfrontUtils::contentLanguage(Language::getInstance()->getLanguage());
            $sample = WidgetsService::getInstance()->getSample('category-dropdown', $language);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($sample) || !isset($sample['id'], $sample['subcategories']) || !is_array($sample['subcategories'])) {
            return [];
        }
        unset($sample['$comment']);
        return self::$sampleCategoryDropdown = $sample;
    }

    /**
     * `shared.basket`: the shopper's basket as basketContent reads `page.basket` ({@see BasketWidgetData}: the SDK basket
     * untouched, plus each option's text and the warnings with LogiCommerce's own words), and in a field of its own,
     * `mffTaxesIncluded`, whether the store shows its prices with taxes, read as LogiCommerce reads it
     * ({@see MagicfrontProductFunctions::taxesIncluded()}): the amounts the pieces paint follow it. The pieces of the
     * basket panel read it: they paint in the header of every page, where no controller attaches `page.basket`, and they
     * are repainted alone through `widgetContent` after every change of the basket. Without a basket, only the tax flag.
     */
    public static function basketShared(?Basket $basket, bool $taxesIncluded, ?BasketWidgetData $data = null): array {
        $shared = $basket === null ? [] : ($data ?? new BasketWidgetData())->of($basket);
        $shared['mffTaxesIncluded'] = $taxesIncluded;
        return $shared;
    }

    /**
     * The session's basket for {@see basketShared()}. A session that has none yet (its typed property unset) or a basket
     * that cannot be written out leaves it without rows instead of taking the whole page render down: the pieces then
     * paint an empty basket, as the theme's own mini basket would.
     */
    private static function sessionBasketShared(): array {
        // In the canvas the basket pieces paint MagicFront's sample basket (`GET /samples/basket`, the furniture of the
        // sample store, in the language being edited), as a product page paints the sample product: not the editor's
        // own session basket, and not a demo written inside each template (02-11-2026). Marked `mffSample`.
        if (RenderMode::isCanvasMode()) {
            $sample = self::sampleBasket();
            if ($sample !== null) {
                return $sample;
            }
        }
        $taxesIncluded = MagicfrontProductFunctions::taxesIncluded();
        try {
            return self::basketShared(Session::getInstance()->getBasket(), $taxesIncluded);
        } catch (\Throwable) {
            return self::basketShared(null, $taxesIncluded);
        }
    }

    /**
     * `shared.calendar`: the commerce's calendar as the widgets' date pickers read it — `firstDayOfWeek` (0 = Sunday …
     * 6 = Saturday, {@see WeekStart}). Empty when LogiCommerce has no calendar: a widget then keeps its own default
     * (#5064: the account's birthday pickers stay on Monday, as before).
     */
    private static function buildCalendar(): array {
        $calendar = ['datePattern' => Utils::getJSDatePatternByLocale(Session::getInstance()->getGeneralSettings()->getLocale())];
        $firstDayOfWeek = WeekStart::fromCalendar(DateTimeFormatter::getCalendar());
        return $firstDayOfWeek === null ? $calendar : $calendar + ['firstDayOfWeek' => $firstDayOfWeek];
    }

    /**
     * Synthetic `shared.paths` key for the plugin's own AJAX route. It is not a FWK RouteType, so it is
     * not in {@see RoutePaths::getRouteTypePaths()}; publishing it here lets a widget build the endpoint
     * from `shared.paths` like every other action URL instead of hardcoding `/lc_ecom_internal/...`,
     * which breaks on any commerce whose INTERNAL_PREFIX or internal URL prefix differs.
     */
    public const PLUGIN_ROUTE_KEY = 'MFF_PLUGIN_ROUTE';

    /** @return array<string, string> */
    private function buildPaths(): array {
        if (!class_exists(RoutePaths::class)) {
            return [];
        }
        $paths = [];
        foreach (array_keys(RoutePaths::getRouteTypePaths()) as $routeType) {
            try {
                $paths[$routeType] = RoutePaths::getPath($routeType);
            } catch (\Throwable) {
                continue;
            }
        }
        $paths[self::PLUGIN_ROUTE_KEY] = '/' . INTERNAL_PREFIX . '/resources/plugin_route/' . ComLogicommerceMagicfrontController::PLUGIN_MODULE;
        return $paths;
    }

    /**
     * Session getters excluded from the `shared.session` mirror: they trigger API calls / are heavy /
     * are sensitive / are not session DATA (framework objects). Everything else is carried verbatim.
     */
    private const SESSION_SKIP = [
        'getInstance',
        'getValue',
        'getValues',
        'getOrders',
        'getOrder',
        'getBasketGridProducts',
        'getExpressCheckoutPlugins',
        'getBasketToken',
        'getDefaultTheme',
        'getDefaultRoute',
        'getNavigationHash',
        'getWarnings',
    ];

    /**
     * `shared.session` mirrors the WHOLE FWK `session` global: every public no-arg `get*` (minus
     * {@see self::SESSION_SKIP}) becomes a key (getBasket→`basket`, getGeneralSettings→`generalSettings`,
     * …), carried verbatim. So a widget reads `shared.session.<anything session exposes>` (e.g.
     * `shared.session.basket.account`, `shared.session.generalSettings.country`) and NEW session fields
     * flow through with no plugin change. Works on full-page AND per-widget AJAX render (uses the
     * session singleton, not the ProviderContext which has no session there).
     *
     * @return array
     */
    private function buildSessionShared(): array {
        return $this->mirrorObject(Session::getInstance(), self::SESSION_SKIP);
    }

    /**
     * `shared.settings` mirrors the WHOLE `EcommerceSettings` — every setting carried verbatim, so a
     * widget reads `shared.settings.<anything>` (e.g. `accountRegisteredUsersSettings.cardinalityPlus`)
     * and NEW settings flow through with no plugin change.
     *
     * @return array
     */
    private function buildSettingsShared(): array {
        $settings = Application::getInstance()->getEcommerceSettings();
        return $settings !== null ? $this->mirrorObject($settings) : [];
    }

    /**
     * Getters the mirror never calls, at ANY depth, because calling them writes on the platform. FWK
     * `SessionShoppingList::getDefaultOneId()` CREATES the account's default shopping list when the session
     * holds no id: mirroring `shared.session` fired that POST on every page view (403 for a visitor, a new
     * «Favoritos» list for a customer without one). The default list still travels in
     * `shared.session.shoppingList.shoppingLists`, each list with its `defaultOne` flag.
     */
    private const WRITING_GETTERS = ['getDefaultOneId'];

    /** Recursion depth for mirroring nested plain (non-JsonSerializable) objects — bounds Route/Theme trees. */
    private const MIRROR_DEPTH = 3;

    /**
     * Mirror an object's public no-arg `get*` methods into an array (getBasket→`basket`), each carried
     * verbatim (per the data-carrier rule — NEVER hand-pick fields). $skip excludes getter names that
     * trigger API calls / are heavy / sensitive / are not data. Values that are JsonSerializable (SDK
     * `Element` DTOs like Basket/Account) serialize via `json_encode`; plain framework objects (private
     * props, no `jsonSerialize` → would encode to `{}`, e.g. `SessionGeneralSettings`) are recursively
     * mirrored so their getters survive too.
     *
     * @param string[] $skip
     * @return array
     */
    private function mirrorObject(object $obj, array $skip = []): array {
        return json_decode(json_encode($this->mirrorGetters($obj, $skip, self::MIRROR_DEPTH)), true) ?? [];
    }

    /**
     * @param string[] $skip
     * @return array
     */
    private function mirrorGetters(object $obj, array $skip, int $depth): array {
        $out = [];
        foreach ((new \ReflectionObject($obj))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic() || $method->getNumberOfRequiredParameters() > 0) {
                continue;
            }
            if (strncmp($name, 'get', 3) !== 0 || in_array($name, $skip, true) || in_array($name, self::WRITING_GETTERS, true)) {
                continue;
            }
            try {
                $out[lcfirst(substr($name, 3))] = $this->mirrorValue($method->invoke($obj), $depth);
            } catch (\Throwable $e) {
            }
        }
        return $out;
    }

    /** A JsonSerializable object / scalar / array is kept as-is; a plain object is recursively mirrored. */
    private function mirrorValue(mixed $value, int $depth): mixed {
        if (is_object($value) && !($value instanceof \JsonSerializable)) {
            return $depth > 0 ? $this->mirrorGetters($value, [], $depth - 1) : null;
        }
        return $value;
    }

    /** @return array serialize an ElementCollection (or [] when absent) */
    private function serialize(mixed $collection): array {
        if (!$collection instanceof ElementCollection) {
            return [];
        }
        $serialized = json_decode(json_encode($collection->getItems()), true);
        return is_array($serialized) ? $serialized : [];
    }

    /**
     * Country code → localized name, resolved ONCE per request. `Utils::getCountryNameByCountryCode`
     * memoizes a single code per call and re-fetches the WHOLE geolocation countries list on every
     * miss (one HTTPS round-trip per country when the Redis object cache is off), so building the
     * map from one `Utils::getCountries()` call replaces N remote calls with one.
     *
     * @return array<string,string>
     */
    private function countryNameMap(): array {
        if ($this->countryNameMap === null) {
            $map = [];
            foreach (Utils::getCountries()->getItems() as $country) {
                $map[$country->getCode()] = $country->getName();
            }
            $this->countryNameMap = $map;
        }
        return $this->countryNameMap;
    }

    /** @return array Commerce country settings items, fetched once per request. */
    private function countrySettingsItems(): array {
        if ($this->countrySettingsItems === null) {
            $countrySettings = Application::getInstance()->getCountriesSettings(Language::getInstance()->getLanguage());
            $this->countrySettingsItems = $countrySettings?->getItems() ?? [];
        }
        return $this->countrySettingsItems;
    }

    /** @return array */
    private function buildCountries(): array {
        $countries = [];
        $names = $this->countryNameMap();
        foreach ($this->countrySettingsItems() as $country) {
            $countries[] = [
                'code' => $country->getCode(),
                'name' => $names[$country->getCode()] ?? '',
                'raw'  => json_decode(json_encode($country), true),
            ];
        }
        return $countries;
    }

    /** @return array country code → localized name */
    private function buildCountryNames(): array {
        $names = [];
        $map = $this->countryNameMap();
        foreach ($this->countrySettingsItems() as $country) {
            $names[$country->getCode()] = $map[$country->getCode()] ?? '';
        }
        return $names;
    }

    /**
     * @return array
     */
    private function buildLocationData(): array {
        $defaultCountry = $this->getDefaultCountry();
        $provinces = [];
        foreach ($this->getDefaultCountryLocations() as $location) {
            $provinces[] = [
                'locationId' => $location->getLocationId(),
                'value'      => $location->getValue(),
                'raw'        => json_decode(json_encode($location), true),
            ];
        }
        return [
            'defaultCountryCode'  => $defaultCountry?->getCode(),
            'postalCodeInCountry' => $defaultCountry?->getPostalCodeType() === PostalCodeType::STATE_CITY_POSTAL_CODE,
            'provinces'           => $provinces,
            'locationMode'        => $this->locationMode(),
        ];
    }

    /** LMS location-search mode: ZIP+city license wins, else city, else '' (no license). */
    private function locationMode(): string {
        if (LmsService::getLocationSearchZipCityLicense()) {
            return LmsService::LOCATION_SEARCH_ZIP_CITY;
        }
        if (LmsService::getLocationSearchCityLicense()) {
            return LmsService::LOCATION_SEARCH_CITY;
        }
        return '';
    }
}
