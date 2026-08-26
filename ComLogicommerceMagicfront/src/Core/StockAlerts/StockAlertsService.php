<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\StockAlerts;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Stock-alerts data CARRIER for the `stockAlerts` widget. Pure transport: fetches the account's stock-alert
 * subscriptions ({@see \SDK\Services\UserService::getStockAlerts} → ACCOUNTS_REGISTERED_USERS_STOCK_ALERTS,
 * NOT paginated) and hands the COMPLETE raw items to the widget — `toArray()['items']` verbatim, no
 * field-picking. The widget owns display + the LC-owned unsubscribe form; see the store
 * fwk/themes/core/macros/modes/bootstrap5/user/stockAlerts.html.twig.
 *
 * `items` are `UserStockAlert::toArray()` — each carries id, email, subscriptionDate and product
 * {name, url, images.{small,medium,large}Image, productCombination.combinationValues[].{optionName,valueName}}.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\StockAlerts
 */
class StockAlertsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $collection = Loader::service(Services::USER)->getStockAlerts();
        $items      = $collection instanceof ElementCollection ? $collection->toArray()['items'] : [];
        return ['items' => $items];
    }
}
