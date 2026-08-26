<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use FWK\Core\Controllers\Traits\AddDefaultCountryAndLocationsTrait;
use FWK\Core\Resources\Language;
use FWK\Core\Resources\RoutePaths;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Utils;
use FWK\Services\LmsService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetLocator;
use SDK\Application;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Enums\PostalCodeType;

/**
 * Provides the platform-neutral `shared.*` container consumed by ANY widget (not just account) — the
 * FWK `session`/`settings` globals mirrored whole (`shared.session`, `shared.settings`) plus derived
 * env data (`countries`, `countryNames`, `locationData`, `companyRoles`, `customTags`); base `licenses`
 * come from {@see ProviderRegistry::baseShared()}. A widget reads uniform `shared.*` keys regardless of
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

    /** The widget types whose business data this provider fetches (into `page.*`) via {@see DataWidgetRegistry}. */
    public function handledTypes(): array {
        return DataWidgetRegistry::types();
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
        if (empty($ctx->session()?->getBasket()?->getAccount()?->getId())) {
            return;
        }
        foreach (DataWidgetRegistry::types() as $type) {
            if ($ctx->hasAnyType([$type])) {
                DataWidgetRegistry::fetchInto($pages, $type, WidgetLocator::findByType($pages, $type));
            }
        }
    }

    /**
     * @return array `shared.*` extras (session/countries/countryNames/locationData/companyRoles/customTags)
     */
    public function sharedData(ProviderContext $ctx): array {
        return [
            'session'      => $this->buildSessionShared(),
            'settings'     => $this->buildSettingsShared(),
            'countries'    => $this->buildCountries(),
            'countryNames' => $this->buildCountryNames(),
            'locationData' => $this->buildLocationData(),
            'companyRoles' => $this->serialize($ctx->getData('companyRoles')),
            'customTags'   => $this->serialize($ctx->getData('customTags')),
            'paths'        => $this->buildPaths(),
        ];
    }

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
        return $paths;
    }

    /**
     * Session getters excluded from the `shared.session` mirror: they trigger API calls / are heavy /
     * are sensitive / are not session DATA (framework objects). Everything else is carried verbatim.
     */
    private const SESSION_SKIP = [
        'getInstance', 'getValue', 'getValues', 'getOrders', 'getOrder', 'getBasketGridProducts',
        'getLockedStocksAggregateData', 'getExpressCheckoutPlugins', 'getBasketToken',
        'getDefaultTheme', 'getDefaultRoute', 'getNavigationHash', 'getWarnings',
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
            if (strncmp($name, 'get', 3) !== 0 || in_array($name, $skip, true)) {
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

    /** @return array */
    private function buildCountries(): array {
        $countries = [];
        $countrySettings = Application::getInstance()->getCountriesSettings(Language::getInstance()->getLanguage());
        foreach (($countrySettings?->getItems() ?? []) as $country) {
            $countries[] = [
                'code' => $country->getCode(),
                'name' => Utils::getCountryNameByCountryCode($country->getCode()),
                'raw'  => json_decode(json_encode($country), true),
            ];
        }
        return $countries;
    }

    /** @return array country code → localized name */
    private function buildCountryNames(): array {
        $names = [];
        $countrySettings = Application::getInstance()->getCountriesSettings(Language::getInstance()->getLanguage());
        foreach (($countrySettings?->getItems() ?? []) as $country) {
            $names[$country->getCode()] = Utils::getCountryNameByCountryCode($country->getCode());
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
