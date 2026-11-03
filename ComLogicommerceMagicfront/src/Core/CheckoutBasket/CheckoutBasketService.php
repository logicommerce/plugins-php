<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutBasket;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use FWK\Core\ViewHelpers\ViewHelpers;
use FWK\ViewHelpers\Basket\Macro\BasketContent;
use Plugins\ComLogicommerceMagicfront\Core\Providers\DtoArray;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Enums\BasketWarningCode;

/**
 * Basket-rows data CARRIER for the `checkoutBasket` widget.
 *
 * The rows themselves are already in `shared.session.basket`, but the store never renders that raw: the
 * `basketContent` macro is fed by {@see \FWK\ViewHelpers\Basket\Macro\BasketContent}, which per row derives
 * the warning severity, the offer flag, the locked-stock timer and the quantity CONTROL's html, and per
 * basket the empty flag, the warnings block, the footer rows, the disclosure and the selectable-gift
 * applied-discount hashes. Rebuilding that in Twig would re-implement FWK, so — exactly like
 * `deliveriesView` — this calls the SAME view helper the store calls and ships its result.
 *
 * The quantity control it produces carries `data-lc-row-type` and the `quantity<hash>` field name that
 * LC's `fillDataForm` serializes, which is why those attributes stay: the checkout CORE
 * (`recalculateBasket` / `fillDataForm` / `submit`) is still LC's and reads them as DATA.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutBasket
 */
class CheckoutBasketService implements WidgetDataService {

    /** Checkout-step warning codes the store hides until the step that can resolve them. */
    private const STEP_WARNING_CODES = [
        BasketWarningCode::NEEDS_PAYMENTSYSTEM,
        BasketWarningCode::NEEDS_DELIVERY,
        BasketWarningCode::INVALID_BILLING_ADDRESS,
        BasketWarningCode::INVALID_SHIPPING_ADDRESS,
    ];

    /** The one-page checkout's basket and warnings blocks: the store's `oscConfiguration.basketContent`, fixed here. */
    public const OSC_ARGUMENTS = [
        'hiddenWarningCodes' => self::STEP_WARNING_CODES,
        'editable' => true,
        'quantityPlugin' => true,
        'saveForLater' => true,
        'mode' => BasketContent::MODE_DISCLOSURE,
    ];

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $session = Session::getInstance();
        $basket  = $session->getBasket();
        if ($basket === null) {
            return [];
        }
        $theme     = Theme::getInstance();
        $arguments = ['basket' => $basket] + self::OSC_ARGUMENTS;
        $viewHelpers = new ViewHelpers(Language::getInstance(), $theme, $session);

        $properties = DtoArray::normalize($viewHelpers->getBasket()->basketContentMacro($arguments));

        return json_decode(json_encode($properties), true) ?? [];
    }
}
