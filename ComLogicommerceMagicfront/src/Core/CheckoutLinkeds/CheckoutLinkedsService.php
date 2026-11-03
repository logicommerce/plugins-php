<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutLinkeds;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Linked-product sections for the `checkoutLinkeds` widget. Pure transport: hands the raw
 * {@see \SDK\Dtos\Catalog\Linked} collection to the widget verbatim (`sectionId`, `name`, `products`).
 *
 * Registered in {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry} so the
 * widget's own `widgetContent` reload re-renders with fresh data — the widget no longer carries
 * `data-lc-oscac`, so LC never refreshes it from the store's `osc_linkeds` HTML endpoint. The
 * full-page render is served by {@see \Plugins\ComLogicommerceMagicfront\Controllers\Checkout\CheckoutController},
 * which also covers anonymous visitors.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutLinkeds
 */
class CheckoutLinkedsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $collection = Loader::service(Services::BASKET)->getLinkedProducts();
        if (!$collection instanceof ElementCollection) {
            return [];
        }
        return json_decode(json_encode($collection), true);
    }
}
