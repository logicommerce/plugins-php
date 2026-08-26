<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Checkout;

use FWK\Controllers\Checkout\CheckoutController as FWKCheckoutController;
use FWK\Core\Controllers\Controller;
use FWK\Enums\ControllerData;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the storefront one-page-checkout route (RouteType::CHECKOUT).
 *
 * MagicFront flow: the checkout page is a SINGLETON MagicFront page (pId 'mff_CHECKOUT') whose widget
 * blob is the onePageCheckout template. FWK's base CheckoutController keeps its own OSC data array in
 * `controllerItem` (deliveries, paymentSystems, addresses, userForm/customerForm from FormFactory) and
 * enqueues the store's checkout JS (lc.oneStepCheckout.js / lc.forms.js) — both must survive intact so
 * the store's own OSC logic drives the widget at runtime. We do NOT overwrite `controllerItem` with the
 * page (unlike HomeController): the OSC array must stay for the store macros.
 *
 * The 'mff_CHECKOUT' blob is resolved generically by {@see MagicfrontTrait::magicfrontPage()} (by pId,
 * not from controllerItem). The route product/category hooks stay no-ops; the whole OSC controller data
 * is attached verbatim onto the widget as `page.checkout` by {@see self::attachCheckoutData()}. The
 * onePageCheckout widget renders its OWN `<form data-lc-form="oneStepCheckout">` (action from the FWK
 * `routePaths` global, exactly like the store's OSCForm macro) — the plugin does no HTML rewriting.
 *
 * When no 'mff_CHECKOUT' page exists magicfrontPage() is null and the trait renders nothing (the plugin
 * only owns this route when CHECKOUT is in `availablepages`, or in preview via mfToken).
 *
 * @see FWKCheckoutController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Checkout
 */
class CheckoutController extends FWKCheckoutController {
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
        $this->attachCheckoutData();
    }

    /**
     * Attach the WHOLE OSC controller data onto the onePageCheckout widget as `page.checkout`, verbatim.
     * Data-carrier rule ([[feedback_php_is_data_carrier]]): the plugin is a mover, not an analyst — it does
     * NOT hand-pick the subset a widget happens to need; it ships the COMPLETE object the controller built
     * (deliveries, paymentSystems, addresses{billing,shipping}, customerForm, userForm, defaultSelectedCountry,
     * defaultSelectedCountryLocations, pluginRewardPoints, pickupPointProviders, …) serialized as-is. The
     * widget reads whatever it needs from that (e.g. `page.checkout.addresses.billingAddresses`); the
     * selected-address ids / login state come from the whole-session mirror in `shared.session.*`.
     */
    private function attachCheckoutData(): void {
        $checkout = json_decode(json_encode($this->getControllerData(Controller::CONTROLLER_ITEM)), true);
        $checkout['deliveriesView'] = $this->buildDeliveriesView();
        PageRelationResolver::attachWidgetData($this->pages, ['setCheckout' => $checkout]);
    }

    /**
     * The deliveries VIEW-MODEL as FWK itself computes it, attached as `page.checkout.deliveriesView`.
     *
     * The raw `deliveries` collection carries no warning texts and no product names: the store's
     * `basketMacros.deliveries` gets them from {@see BasketViewHelper::deliveriesMacro()}, which fills
     * `outputWarnings` (message built from the language sheet + warning attributes) and joins each
     * delivery row back to its basket row. Rebuilding that in Twig would mean re-implementing FWK
     * logic, so the plugin calls the SAME view helper the store calls and ships its result — still a
     * carrier ([[feedback_php_is_data_carrier]]), just of a value FWK produced.
     *
     * Guarded exactly like the store template (`{% if deliveries is not null %}`): with `useOSCAsync`
     * on, or with no basketToken, the controller leaves `deliveries` null and the helper must not run.
     *
     * @return array
     */
    private function buildDeliveriesView(): array {
        $item = $this->getControllerData(Controller::CONTROLLER_ITEM);
        $deliveries = is_array($item) ? ($item[self::DELIVERIES] ?? null) : null;
        if (is_null($deliveries)) {
            return [];
        }
        $arguments = array_merge(
            self::getTheme()->getConfiguration()->getCommerce()->getOscConfiguration()?->getDeliveries() ?? [],
            [
                'basket' => $this->getSession()?->getBasket(),
                'deliveries' => $deliveries,
                'physicalLocations' => $this->getControllerData(self::PHYSICAL_LOCATIONS),
                'pickupPointProviders' => $item[self::PICKUP_POINT_PROVIDERS] ?? null,
            ]
        );
        $view = $this->getControllerData(ControllerData::VIEW_HELPERS)->getBasket()->deliveriesMacro($arguments);
        return json_decode(json_encode($view), true);
    }
}
