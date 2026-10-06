<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Checkout;

use FWK\Controllers\Checkout\BasketController as FWKBasketController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
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
 * The basket the controller built is attached verbatim onto the widgets as `page.basket`
 * ([[feedback_php_is_data_carrier]]): the widget reads items, totals and basketWarnings from it. With no
 * basket (no session yet) `page.basket` stays [] and the widget shows its empty state.
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
        PageRelationResolver::attachWidgetData($this->pages, ['setBasket' => json_decode(json_encode($basket), true)]);
    }
}
