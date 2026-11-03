<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutShippings;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use FWK\Core\ViewHelpers\ViewHelpers;
use FWK\Core\Resources\Utils;
use FWK\Dtos\Catalog\PhysicalLocation;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\PhysicalLocationsCollection;
use SDK\Enums\DeliveryType;
use SDK\Enums\PickingDeliveryType;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Services\Parameters\Groups\Document\PickupPointProvidersParametersGroup;

/**
 * Delivery data CARRIER for the `checkoutShippings` widget.
 *
 * Same reason as {@see \Plugins\ComLogicommerceMagicfront\Core\CheckoutPayments\CheckoutPaymentsService}:
 * with `useOSCAsync` on, {@see \FWK\Controllers\Checkout\CheckoutController::setControllerBaseBatchData()}
 * sets `DELIVERIES` to null and leaves the module to the browser, so the widget fetches its own.
 *
 * Copies the FWK rule: the deliveries a widget can render are not one API call but the picking and the
 * shipping deliveries MERGED, with the selected provider pickup point merged in on top, and the physical
 * locations derived from them. The raw collection then still carries no warning texts and no product
 * names — the store gets those from {@see \FWK\ViewHelpers\BasketViewHelper::deliveriesMacro()}, so this
 * calls the SAME view helper and ships its result (still a carrier, of a value FWK produced).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutShippings
 */
class CheckoutShippingsService implements WidgetDataService {

    private const PICKING  = 'mffPickingDeliveries';
    private const SHIPPING = 'mffShippingDeliveries';
    private const SELECTED = 'mffSelectedProviderPickupPoint';
    private const PROVIDERS = 'mffPickupPointProviders';

    /** Id of the PICKING location whose delivery is selected, or null. */
    private ?int $defaultPhysicalLocationId = null;

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $basketService = Loader::service(Services::BASKET);
        $requests      = new BatchRequests();
        $basketService->addGetPhysicalLocationPickingDeliveries($requests, self::PICKING);
        $basketService->addGetShippingDeliveries($requests, self::SHIPPING);
        $basketService->addGetProviderPickupPointPickingDeliveriesSelectedPickupPoint($requests, self::SELECTED);
        Loader::service(Services::ORDER)->addGetPickupPointProviders($requests, self::PROVIDERS, $this->providerParams());
        $result = BatchService::getInstance()->send($requests);

        $deliveries = $result[self::PICKING] ?? null;
        if ($deliveries instanceof ElementCollection) {
            $deliveries->merge($result[self::SHIPPING] ?? null);
        } else {
            $deliveries = $result[self::SHIPPING] ?? null;
        }
        if (!$deliveries instanceof ElementCollection) {
            return [];
        }
        $deliveries->merge($result[self::SELECTED] ?? null);

        return $this->view($deliveries, $result[self::PROVIDERS] ?? null);
    }

    /**
     * FWK scopes the providers to the visitor's selected country when there is one.
     */
    private function providerParams(): PickupPointProvidersParametersGroup {
        $params = new PickupPointProvidersParametersGroup();
        $countryId = Utils::getSelectedCountryId();
        if ($countryId !== null && $countryId !== '') {
            $params->setCountryCode($countryId);
        }
        return $params;
    }

    /**
     * The Maps key the picker's map container needs, resolved exactly as
     * {@see \FWK\ViewHelpers\Util\Macro\PhysicalLocations} resolves it: the `apiKey` property of the
     * maps plugin.
     */
    private function mapsApiKey(): ?string {
        $properties = Loader::service(Services::PLUGIN)->getMapsPluginProperties();
        if ($properties === null) {
            return null;
        }
        foreach ($properties->getProperties() as $property) {
            if ($property->getName() === 'apiKey') {
                return $property->getValue();
            }
        }
        return null;
    }

    /**
     * Copied from {@see \FWK\Core\Controllers\Traits\SetPhysicalLocationsFromDeliveries}: the picking
     * modal lists PhysicalLocations DERIVED from the PICKING deliveries (a provider pickup point becomes a
     * synthetic location with a negative id), not a separate API resource. Without them the picking
     * selector renders empty.
     */
    private function physicalLocations(ElementCollection $deliveries): ElementCollection {
        $items = [];
        $count = 0;
        foreach ($deliveries->getItems() as $delivery) {
            if ($delivery->getType() !== DeliveryType::PICKING) {
                continue;
            }
            $count++;
            if ($delivery->getMode()->getType() === PickingDeliveryType::PROVIDER_PICKUP_POINT) {
                $raw = $delivery->getMode()->getProviderPickupPoint()->toArray();
                $raw['id'] = 0 - $count;
            } else {
                $raw = $delivery->getMode()->getPhysicalLocation()->toArray();
            }
            $raw['hash'] = $delivery->getHash();
            $location = new PhysicalLocation($raw);
            $location->setDelivery($delivery);
            if ($delivery->getSelected()) {
                $this->defaultPhysicalLocationId = $location->getId();
            }
            $items[] = $location;
        }
        if ($count === 0) {
            return new ElementCollection();
        }
        $filter = $deliveries->getFilter();
        return new PhysicalLocationsCollection([
            'items' => $items,
            'filter' => (is_null($filter) || !method_exists($filter, 'getPhysicalLocations') || is_null($filter->getPhysicalLocations()))
                ? [] : $filter->getPhysicalLocations()->toArray(),
            'pagination' => ['page' => 1, 'perPage' => $count, 'totalItems' => $count, 'totalPages' => 1],
        ]);
    }

    /**
     * @return array
     */
    private function view(ElementCollection $deliveries, mixed $providers): array {
        $theme = Theme::getInstance();
        $arguments = [
            'basket' => Session::getInstance()->getBasket(),
            'deliveries' => $deliveries,
            'physicalLocations' => $this->physicalLocations($deliveries),
            'pickupPointProviders' => $providers,
        ];
        $viewHelpers = new ViewHelpers(Language::getInstance(), $theme, Session::getInstance());
        $view = json_decode(json_encode($viewHelpers->getBasket()->deliveriesMacro($arguments)), true) ?? [];

        /* The pickup-point PICKER (country/state selects, map, store list) that the store renders into the
           `#physicalLocations` / `#pickupPointProviders` modal bodies is built by
           {@see \FWK\ViewHelpers\Util\Macro\PhysicalLocations}, whose output is FormFactory ELEMENT
           objects - they do not survive json_encode, so unlike deliveriesMacro this one cannot be shipped
           as a value. The widget rebuilds that markup from the raw data instead; which parts of the picker
           show are the widget's settings, the Maps key is the one the view helper resolves from the maps plugin. */
        $view['pickerConfig'] = [
            'mapsApiKey' => $this->mapsApiKey(),
            /* Drives which country/state the picker opens on: LC's initFilterPhysicalLocations reads it
               from the hidden initSelectors input, exactly as SetPhysicalLocationsFromDeliveries feeds
               the store's `defaultPhysicalLocationId`. */
            'defaultPhysicalLocationId' => $this->defaultPhysicalLocationId,
            'selectedCountryId' => Utils::getSelectedCountryId(),
        ];

        return $view;
    }
}
