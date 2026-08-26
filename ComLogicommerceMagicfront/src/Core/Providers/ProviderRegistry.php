<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use SDK\Application;

/**
 * Single source of truth for the {@see WidgetDataProvider} set and the shared-data collection over it,
 * so the full-page render ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait})
 * and the per-widget AJAX render ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\GetWidgetHandler})
 * never drift apart on which providers exist or how their `sharedData()` is merged.
 *
 * Mirrors the SDK/FWK habit of centralising a registry (cf. `Loader`/`Services`) instead of `new`-ing the
 * same collaborators at each call site.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class ProviderRegistry {

    /**
     * The default provider set, keyed by widget TYPE (not route) so their data attaches wherever the
     * widget is placed. Phase 0: only the account/session group; product/category groups migrate here.
     *
     * @return WidgetDataProvider[]
     */
    public static function all(): array {
        return [
            new SharedDataProvider(),
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
