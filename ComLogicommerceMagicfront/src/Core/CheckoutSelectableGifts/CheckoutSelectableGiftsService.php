<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutSelectableGifts;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;

/**
 * Selectable-gift groups for the `checkoutSelectableGifts` widget, keyed by discount id and carried
 * verbatim (`{discountId: {items, discount}}`) exactly as the commerce controller derives them from
 * the basket's applied discounts.
 *
 * Registered in {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry} so the
 * widget's own `widgetContent` reload re-renders with fresh data — the widget no longer carries
 * `data-lc-oscac`, so LC never refreshes it from the store's `osc_selectable_gifts` HTML endpoint.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutSelectableGifts
 */
class CheckoutSelectableGiftsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $basket = Session::getInstance()->getBasket();
        if ($basket === null) {
            return [];
        }
        $gifts = Loader::service(Services::DISCOUNT)->getAllSelectableGiftProductsFromBasket($basket);
        return json_decode(json_encode($gifts), true);
    }
}
