<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutPlaceOrder;

use FWK\Core\Resources\Session;
use FWK\ViewHelpers\Basket\Macro\Buttons;
use Plugins\ComLogicommerceMagicfront\Core\Checkout\CheckoutRoute;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;

/**
 * End-order button STATE for the `checkoutPlaceOrder` widget.
 *
 * The button is not static markup: {@see \FWK\ViewHelpers\Basket\Macro\Buttons} decides from the basket
 * whether it renders enabled with `data-end-order="true"` or disabled with `initializeDisabled`, and which
 * of the "needs payment" / "needs shipping" / "address error" messages goes with it. That is exactly why
 * LC marks the `buttons` module `autoRefresh: true` and re-renders it after every recalculate — this
 * widget reloads itself through `widgetContent` for the same reason.
 *
 * Calls the SAME view helper the store calls, per the carrier rule.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutPlaceOrder
 */
class CheckoutPlaceOrderService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $session = Session::getInstance();
        $basket  = $session->getBasket();
        if ($basket === null) {
            return [];
        }
        $arguments = ['basket' => $basket];
        /* NOT via BasketViewHelper::buttonsMacro(): that passes `$theme->getRouteType()`, and Buttons
           OVERWRITES routeType after merging the arguments, so it cannot be injected. On the widgetContent
           AJAX render the theme's route is the plugin route, not CHECKOUT, and `validOutput` would come back
           false — the end-order button would render as nothing every time the module refreshed.
           The step comes from {@see CheckoutRoute::current()}, which is also what decides the button set:
           the store only paints Continue shopping / Clear basket / Recalculate on the basket step, and the
           action the end-order button posts is the NEXT step's route type. */
        return json_decode(json_encode((new Buttons($arguments, CheckoutRoute::current()))->getViewParameters()), true) ?? [];
    }
}
