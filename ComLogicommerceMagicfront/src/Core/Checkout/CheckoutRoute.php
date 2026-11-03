<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Checkout;

use FWK\Core\Theme\Theme;
use FWK\Enums\RouteTypes\InternalCheckout;
use Plugins\ComLogicommerceMagicfront\Core\Services\RouteResolver;
use SDK\Enums\RouteType;

/**
 * Which checkout step the current request is painting.
 *
 * The store feeds the same basket macro a DIFFERENT argument set per step: `Content/Checkout/Basket`
 * passes editable/saveForLater/quantityPlugin, while `Content/Checkout/PaymentAndShipping` passes only the
 * hidden warning codes, because there the basket is a read-only recap. That single difference decides
 * whether the units column comes back as an input or as plain text
 * ({@see \FWK\ViewHelpers\Basket\Macro\BasketContent::getQuantityOutput}), so a checkout data service must
 * know its step before it can pick arguments.
 *
 * The theme's route is the source, and it is authoritative on the full-page render, which is how every
 * checkout step is served — the store posts the whole form to the next step, it never swaps a step over
 * AJAX. On the plugin's `widgetContent` AJAX route the theme reports that plugin route and no step can be
 * derived, so {@see self::current()} answers CHECKOUT: the one-page checkout's own value, leaving its
 * behaviour untouched.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Checkout
 */
class CheckoutRoute {

    /** @var string[] */
    private const STEPS = [
        RouteType::CHECKOUT,
        RouteType::CHECKOUT_BASKET,
        RouteType::CHECKOUT_CUSTOMER,
        RouteType::CHECKOUT_PAYMENT_AND_SHIPPING,
    ];

    /**
     * The native customer-step routes besides CHECKOUT_CUSTOMER: the guest form, the new-customer form and the
     * create-account form. With the one step checkout on, FWK's
     * {@see \FWK\Core\Controllers\Traits\CheckoutRedirectTrait} sends them to the checkout.
     *
     * @return string[]
     */
    public static function customerRoutes(): array {
        return [RouteType::CHECKOUT_GUEST, RouteType::CHECKOUT_CUSTOMER_NEW_REGISTER, RouteType::CHECKOUT_CREATE_ACCOUNT];
    }

    /**
     * Every route FWK steers by the commerce's one step checkout setting: the checkout, its native steps and
     * customer routes, the denied order and the express checkout ones.
     *
     * @return string[]
     */
    public static function oneStepRoutes(): array {
        return array_merge(self::STEPS, self::customerRoutes(), [RouteType::CHECKOUT_DENIED_ORDER, RouteType::EXPRESS_CHECKOUT_CANCEL, RouteType::EXPRESS_CHECKOUT_RETURN, InternalCheckout::EXPRESS_CHECKOUT]);
    }

    /** @var string|null per-request memo of the step recovered from the Referer */
    private static ?string $refererStep = null;

    public static function current(): string {
        $routeType = Theme::getInstance()->getRouteType();
        if (in_array($routeType, self::STEPS, true)) {
            return $routeType;
        }
        return self::fromReferer() ?? RouteType::CHECKOUT;
    }

    /**
     * The step the AJAX call came FROM.
     *
     * On the plugin's `widgetContent` route the theme reports that plugin route, so no step can be
     * derived from it and every reloaded widget used to be served the one-page checkout's arguments —
     * which on the payment-and-shipping step would hand the basket recap an editable units column
     * instead of plain text ({@see \FWK\ViewHelpers\Basket\Macro\BasketContent::getQuantityOutput}).
     * The Referer is the page the widget lives on, and {@see WidgetContentHandler::resolveBlobPId()}
     * already resolves it the same way, so the step comes back without changing the widgets' request.
     */
    private static function fromReferer(): ?string {
        if (self::$refererStep !== null) {
            return self::$refererStep === '' ? null : self::$refererStep;
        }
        self::$refererStep = '';
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return null;
        }
        $routeType = (string) (RouteResolver::forPath($referer)?->getType() ?? '');
        if (!in_array($routeType, self::STEPS, true)) {
            return null;
        }
        self::$refererStep = $routeType;
        return $routeType;
    }

    /** The basket step is the only one where the shopper still edits units and removes rows. */
    public static function isBasketStep(): bool {
        return self::current() === RouteType::CHECKOUT_BASKET;
    }
}
