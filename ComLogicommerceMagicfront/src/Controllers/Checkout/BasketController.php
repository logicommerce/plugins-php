<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Checkout;

use FWK\Controllers\Checkout\BasketController as FWKBasketController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BasketWidgetData;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the storefront basket page (RouteType::CHECKOUT_BASKET, /checkout/basket).
 *
 * MagicFront flow: the basket page is a SINGLETON MagicFront page (pId 'mff_BASKET') painted once in the
 * editor, usually around the basketContent widget. FWK's base BasketController fetches the basket (or
 * recalculates it when stock locking is on) under its `basket` data key and keeps its own flow intact;
 * this override only adds the MagicFront page on top, exactly like {@see CheckoutController}.
 *
 * The basket the controller built is attached onto the widgets as `page.basket`: the SDK basket as is, plus the
 * option texts and the warnings LogiCommerce writes in fields of their own
 * ({@see \Plugins\ComLogicommerceMagicfront\Core\Resources\BasketWidgetData}); the widget reads items, totals and
 * mffWarnings from it. With no
 * basket (no session yet) `page.basket` stays [] and the widget shows its empty state. On any other route
 * (the editor's preview tab, or /checkout when one-step checkout is on) the session basket reaches the widget
 * through {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\BasketDataProvider}; this one, attached after
 * the providers, wins here.
 *
 * When no 'mff_BASKET' page is published magicfrontPage() is null and the trait renders nothing (the
 * plugin only owns this route when BASKET is in `availablepages`, or in preview via mfToken).
 *
 * @see FWKBasketController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Checkout
 */
class BasketController extends FWKBasketController {
    use MagicfrontTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->setMagicfrontBatchData($requests);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setMagicfrontData();
        $this->attachBasketData();
    }

    private function attachBasketData(): void {
        $basket = $this->getControllerData(self::BASKET) ?? $this->getSession()?->getBasket();
        if (is_null($basket)) {
            return;
        }
        PageRelationResolver::attachWidgetData($this->pages, ['setBasket' => (new BasketWidgetData())->of($basket)]);
    }
}
