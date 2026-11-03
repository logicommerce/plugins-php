<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutWarnings;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use FWK\Core\ViewHelpers\ViewHelpers;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutBasket\CheckoutBasketService;
use Plugins\ComLogicommerceMagicfront\Core\Providers\DtoArray;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;

/**
 * Basket-warnings data CARRIER for the `checkoutWarnings` widget.
 *
 * The store paints the warnings inside `basketContent`, so the block cannot be moved. Splitting it into
 * its own widget lets the merchant place it anywhere in the checkout, which means it needs its own data:
 * the SAME {@see \FWK\ViewHelpers\Basket\Macro\BasketContent} result the basket widget gets, because
 * `warningsBlockItems` (severity => warnings, each with the message FWK resolves from the language sheet)
 * is only built there. The view helper reads the session basket and calls no API, so computing it twice
 * costs nothing.
 *
 * {@see DtoArray::normalize()} is required: the grouped warnings are `SDK\Dtos\Basket\BasketWarnings\
 * BasketWarning` objects, which only `use ElementTrait` and are therefore NOT `JsonSerializable` —
 * `json_encode()` would emit `{}` and the widget would filter every warning away.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutWarnings
 */
class CheckoutWarningsService implements WidgetDataService {

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
        $arguments = ['basket' => $basket] + CheckoutBasketService::OSC_ARGUMENTS;
        $viewHelpers = new ViewHelpers(Language::getInstance(), $theme, $session);

        $properties = DtoArray::normalize($viewHelpers->getBasket()->basketContentMacro($arguments));

        return json_decode(json_encode($properties), true) ?? [];
    }
}
