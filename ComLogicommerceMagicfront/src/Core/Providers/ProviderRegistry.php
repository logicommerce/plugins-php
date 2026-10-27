<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use SDK\Application;

/**
 * Single source of truth for the {@see WidgetDataProvider} set and the shared-data collection over it,
 * so the full-page render ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait}),
 * the per-widget AJAX render ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\GetWidgetHandler})
 * and the chrome over a page that is not MagicFront's ({@see \Plugins\ComLogicommerceMagicfront\Core\Twig\TwigInitializer})
 * never drift apart on which providers exist or how their `sharedData()` is merged.
 *
 * Mirrors the SDK/FWK habit of centralising a registry (cf. `Loader`/`Services`) instead of `new`-ing the
 * same collaborators at each call site.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class ProviderRegistry {

    /** @var array<string, array> `shared` per sorted family set, built once per request ({@see self::sharedForTypes()}). */
    private static array $sharedByFamilies = [];

    /**
     * The default provider set, keyed by widget TYPE (not route) so their data attaches wherever the
     * widget is placed. The account/session group and the basket (basketContent); product/category groups migrate here.
     *
     * @return WidgetDataProvider[]
     */
    public static function all(): array {
        return [
            new SharedDataProvider(),
            new BasketDataProvider(),
        ];
    }

    /**
     * The full `shared` map for the given context: the always-on base ({@see self::baseShared()})
     * merged with each applicable provider's `sharedData()`. Single builder for BOTH the full-page
     * render and the per-widget AJAX render, so `shared` is universal to every widget and always
     * carries the base data regardless of path (no drift on whether base is present).
     * Takes the provider list so callers can pass a controller-overridden set; use {@see self::all()}
     * for the default.
     *
     * @param WidgetDataProvider[] $providers
     * @return array
     */
    public static function collectShared(array $providers, ProviderContext $ctx): array {
        $shared = self::baseShared();
        foreach ($providers as $provider) {
            if ($provider->appliesTo($ctx)) {
                $shared = array_merge($shared, $provider->sharedData($ctx));
            }
        }
        return $shared;
    }

    /**
     * `shared` for a render with no MagicFront controller behind it to dispatch the providers: the per-widget AJAX
     * render and the chrome of a native page. The same providers and base as the full page, gated to the widget
     * families present, with no route, session or controller data to read.
     *
     * Memoized per request and per family set: the providers are the expensive part (countries, locations… — in a
     * local shop a round of geolocation calls), and a render can ask for the same set more than once (Twig global and
     * template local; `getWidgets` once per widget).
     *
     * The countries and the locations only for the keys in `$sharedNeeds` ({@see SharedNeeds}, #4251): null builds
     * them all, as before.
     *
     * @param string[] $presentTypes widget types, `family` or `family@version`
     * @param string[]|null $sharedNeeds the {@see SharedNeeds::ON_DEMAND} keys the templates of the render read
     * @return array
     */
    public static function sharedForTypes(array $presentTypes, ?array $sharedNeeds = null): array {
        $families = array_values(array_unique(array_map([WidgetTypeCollector::class, 'familyOf'], $presentTypes)));
        sort($families);
        $memoKey = implode(',', $families) . '|' . ($sharedNeeds === null ? '*' : implode(',', $sharedNeeds));
        if (!array_key_exists($memoKey, self::$sharedByFamilies)) {
            $ctx = new ProviderContext(null, null, $families, static fn(string $key): mixed => null, null, false, $sharedNeeds);
            self::$sharedByFamilies[$memoKey] = self::collectShared(self::all(), $ctx);
        }
        return self::$sharedByFamilies[$memoKey];
    }

    /**
     * `shared` for the header, footer and panels painted over a page whose controller is not MagicFront's: search,
     * brands, offers, contact, lost password, the 404, the native checkout steps, a page type with nothing published.
     * Only a MagicFront controller dispatches the providers, so there the chrome got `shared` empty: the account
     * panel's login form went out with `action=""` and LogiCommerce's AJAX login got the page's HTML back instead of
     * its JSON, failing with no message (#4284).
     *
     * Null when the controller already built it (a MagicFront page, whose own `shared` wins) or when none of our
     * widgets is painted.
     *
     * @param array $controllerData the controller's data, as the FWK hands it to the plugin's Twig initializer
     * @param string[] $chromeTypes widget types of the chrome painted on this render
     * @param \Closure(string[], string[]|null): array|null $build the builder; {@see self::sharedForTypes()} unless a test
     *        replaces it
     * @param string[]|null $sharedNeeds the costly keys the chrome's templates read ({@see SharedNeeds}, #4251)
     * @return array|null
     */
    public static function sharedForChrome(array $controllerData, array $chromeTypes, ?\Closure $build = null,
        ?array $sharedNeeds = null): ?array {
        if (array_key_exists(MagicfrontControllerData::SHARED, $controllerData) || $chromeTypes === []) {
            return null;
        }
        return ($build ?? self::sharedForTypes(...))($chromeTypes, $sharedNeeds);
    }

    /**
     * Platform-neutral env data every widget gets regardless of any provider — the commerce data the
     * FWK Twig globals do NOT carry but that any widget may need (mirrors how FWK exposes `settings`
     * globally). Currently the active license PIds (`shared.licenses`), raw and uninterpreted; widgets
     * decide tiers themselves.
     *
     * @return array
     */
    public static function baseShared(): array {
        $licenses = [];
        foreach ((Application::getInstance()->getEcommerceLicenses()?->getLicenses() ?? []) as $license) {
            $pId = $license->getPId();
            if ($pId !== null && $pId !== '') {
                $licenses[$pId] = json_decode(json_encode($license), true);
            }
        }
        return ['licenses' => $licenses];
    }
}
