<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use Plugins\ComLogicommerceMagicfront\Core\Resources\BasketWidgetData;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;

/**
 * The shopper's basket onto the basketContent widget as `page.basket`, on whatever route the page is painted (#7700).
 *
 * Until now only the basket route's controller attached it ({@see \Plugins\ComLogicommerceMagicfront\Controllers\Checkout\BasketController}),
 * and the basket page is not always painted there: the editor's preview tab opens it on the store root, and a commerce
 * with one-step checkout sends /checkout/basket to /checkout (FWK CheckoutRedirectTrait). There the block said «your
 * basket is empty» with products in it. It carries the session basket, the one FWK's own checkout reads
 * (CheckoutController hands `session.basket` to its deliveries macro), in the shape the block reads
 * ({@see BasketWidgetData}). The basket route still attaches its own afterwards (FWK fetches it, or recalculates it when
 * stock locking is on), so there nothing changes.
 *
 * The editor canvas paints the widget's demo rows ({@see ProviderContext::canvasMode()}), so nothing is read there.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class BasketDataProvider implements WidgetDataProvider {

    private const TYPES = ['basketContent'];

    public function handledTypes(): array {
        return self::TYPES;
    }

    public function appliesTo(ProviderContext $ctx): bool {
        return !$ctx->canvasMode() && $ctx->hasAnyType(self::TYPES);
    }

    public function addBatch(BatchRequests $requests, ProviderContext $ctx): void {
    }

    public function attach(?ElementCollection $pages, ProviderContext $ctx): void {
        $basket = $ctx->session()?->getBasket();
        if ($pages === null || $basket === null) {
            return;
        }
        PageRelationResolver::attachWidgetData($pages, ['setBasket' => (new BasketWidgetData())->of($basket)]);
    }

    public function sharedData(ProviderContext $ctx): array {
        return [];
    }
}
