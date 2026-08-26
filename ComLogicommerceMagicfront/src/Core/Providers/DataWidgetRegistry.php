<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use Plugins\ComLogicommerceMagicfront\Core\AddressBook\AddressBookService;
use Plugins\ComLogicommerceMagicfront\Core\Orders\OrdersService;
use Plugins\ComLogicommerceMagicfront\Core\PaymentCards\PaymentCardsService;
use Plugins\ComLogicommerceMagicfront\Core\RegisteredUserData\RegisteredUserDataService;
use Plugins\ComLogicommerceMagicfront\Core\RegisteredUserProfile\RegisteredUserProfileService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\RewardPoints\RewardPointsService;
use Plugins\ComLogicommerceMagicfront\Core\Rmas\RmasService;
use Plugins\ComLogicommerceMagicfront\Core\SalesAgentCustomers\SalesAgentCustomersService;
use Plugins\ComLogicommerceMagicfront\Core\SalesAgentSales\SalesAgentSalesService;
use Plugins\ComLogicommerceMagicfront\Core\ShoppingList\ShoppingListService;
use Plugins\ComLogicommerceMagicfront\Core\StockAlerts\StockAlertsService;
use Plugins\ComLogicommerceMagicfront\Core\Subscriptions\SubscriptionsService;
use Plugins\ComLogicommerceMagicfront\Core\VoucherCodes\VoucherCodesService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Single source of truth mapping an account widget TYPE to the Service that fetches its data and the
 * Page setters that data lands on. Replaces the per-type branch stack in {@see SharedDataProvider}
 * and the per-widget content reloaders it replaced: the full-page render and the `widgetContent`
 * AJAX endpoint now share this one table.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class DataWidgetRegistry {

    /**
     * type => [serviceClass, [pageSetter => fetchResultKey|null]].
     * A null result key assigns the whole fetch payload; a string key extracts that entry (orders /
     * shoppingList return a multi-value payload split across two setters).
     *
     * @var array
     */
    private const MAP = [
        'addressBook'           => [AddressBookService::class,           ['setInvoicingAddresses' => 'invoicing', 'setShippingAddresses' => 'shipping']],
        'orders'                => [OrdersService::class,                ['setOrders' => 'orders', 'setOrdersPagination' => 'pagination', 'setOrdersAccountNames' => 'accountNames']],
        'shoppingList'          => [ShoppingListService::class,          ['setShoppingListRows' => 'rows', 'setShoppingLists' => 'lists']],
        'rewardPoints'          => [RewardPointsService::class,          ['setRewardPoints' => null]],
        'rmas'                  => [RmasService::class,                  ['setRmas' => null]],
        'stockAlerts'           => [StockAlertsService::class,           ['setStockAlerts' => null]],
        'subscriptions'         => [SubscriptionsService::class,         ['setSubscriptions' => null]],
        'paymentCards'          => [PaymentCardsService::class,          ['setPaymentCards' => null]],
        'salesAgentCustomers'   => [SalesAgentCustomersService::class,   ['setSalesAgentCustomers' => null]],
        'salesAgentSales'       => [SalesAgentSalesService::class,       ['setSalesAgentSales' => null]],
        'voucherCodes'          => [VoucherCodesService::class,          ['setVoucherCodes' => null]],
        'registeredUserData'    => [RegisteredUserDataService::class,    ['setRegisteredUserData' => null]],
        'registeredUserProfile' => [RegisteredUserProfileService::class, ['setRegisteredUserProfile' => null]],
    ];

    /** @return string[] every data-widget type this registry can fetch. */
    public static function types(): array {
        return array_keys(self::MAP);
    }

    public static function handles(string $type): bool {
        return isset(self::MAP[$type]);
    }

    /**
     * Fetch $type's data for the current request via its Service and attach it onto every page in $pages
     * (recursively). No-op for an unknown type or a null collection.
     */
    public static function fetchInto(?ElementCollection $pages, string $type, ?PluginPage $widget): void {
        if ($pages === null || !isset(self::MAP[$type])) {
            return;
        }
        [$serviceClass, $setterMap] = self::MAP[$type];
        $data = (new $serviceClass())->fetchForWidget($widget);
        $assignments = [];
        foreach ($setterMap as $setter => $key) {
            $assignments[$setter] = $key === null ? $data : ($data[$key] ?? []);
        }
        PageRelationResolver::attachWidgetData($pages, $assignments);
    }
}
