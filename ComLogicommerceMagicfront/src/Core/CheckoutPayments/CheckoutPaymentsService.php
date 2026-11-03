<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\CheckoutPayments;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Services;
use FWK\Services\BasketService;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\PluginTriggers;
use SDK\Core\Interfaces\PluginPropertyTriggers;
use SDK\Enums\PluginConnectorType;
use SDK\Enums\PluginEvents;
use SDK\Services\Parameters\Groups\PluginConnectorTypeParametersGroup;

/**
 * Payment-system data CARRIER for the `checkoutPayments` widget.
 *
 * The widget cannot read `page.checkout.paymentSystems`: with `useOSCAsync` on — which is this store's
 * setting — {@see \FWK\Controllers\Checkout\CheckoutController::setControllerBaseBatchData()} deliberately
 * sets that value to null and leaves the module for the browser to load. So the widget fetches its own,
 * the same way every other MFF data widget does.
 *
 * Copies the FWK rule from {@see \FWK\Core\Controllers\Traits\AddPluginPaymentSystemTrait}: the raw
 * collection is not enough on its own — a plugin payment system (Redsys, PayPal, …) carries its
 * properties, its connector-item properties and its SELECT_PAYMENT_SYSTEM trigger results on a separate
 * plugin-properties object, which FWK merges in before the macro sees it. Skipping that merge silently
 * drops those payment methods' configuration.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\CheckoutPayments
 */
class CheckoutPaymentsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $basketService  = Loader::service(Services::BASKET);
        $paymentSystems = $basketService->getPaymentSystems();
        if (!$paymentSystems instanceof ElementCollection) {
            return ['items' => []];
        }
        BasketService::mergePaymentSystemsWithPluginProperties($paymentSystems, $this->pluginProperties());

        return $paymentSystems->toArray();
    }

    /**
     * The plugin payment systems' properties, keyed `<module>_<pluginId>` exactly as
     * {@see BasketService::mergePaymentSystemsWithPluginProperties()} looks them up.
     *
     * @return array
     */
    private function pluginProperties(): array {
        $params = new PluginConnectorTypeParametersGroup();
        $params->setConnectorType(PluginConnectorType::PAYMENT_SYSTEM);
        $params->setNavigationHash(Session::getInstance()->getNavigationHash());
        $pluginService = Loader::service(Services::PLUGIN);
        $plugins       = $pluginService->getPlugins($params);
        $properties    = [];
        foreach (($plugins ?? []) as $plugin) {
            $property = $pluginService->getPluginProperties($plugin->getId());
            if ($property === null) {
                continue;
            }
            if (in_array(PluginPropertyTriggers::class, class_implements($property) ?: [], true)) {
                $property->setEventResults(
                    PluginEvents::SELECT_PAYMENT_SYSTEM,
                    PluginTriggers::execute(PluginEvents::SELECT_PAYMENT_SYSTEM, $plugin, Session::getInstance()->getBasket())
                );
            }
            $properties[$property->getPluginModule() . '_' . $property->getPluginId()] = $property;
        }
        return $properties;
    }
}
