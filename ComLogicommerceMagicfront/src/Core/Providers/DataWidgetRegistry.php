<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use Plugins\ComLogicommerceMagicfront\Core\AccountLogin\AccountLoginService;
use Plugins\ComLogicommerceMagicfront\Core\AddressBook\AddressBookService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutLinkeds\CheckoutLinkedsService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutBasket\CheckoutBasketService;
use Plugins\ComLogicommerceMagicfront\Core\Checkout\CheckoutRoute;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutWarnings\CheckoutWarningsService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutPayments\CheckoutPaymentsService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutPlaceOrder\CheckoutPlaceOrderService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutSelectableGifts\CheckoutSelectableGiftsService;
use Plugins\ComLogicommerceMagicfront\Core\CheckoutShippings\CheckoutShippingsService;
use Plugins\ComLogicommerceMagicfront\Core\OrderDetail\OrderDetailService;
use Plugins\ComLogicommerceMagicfront\Core\Orders\OrdersService;
use Plugins\ComLogicommerceMagicfront\Core\PaymentCards\PaymentCardsService;
use Plugins\ComLogicommerceMagicfront\Core\RegisteredUserData\RegisteredUserDataService;
use Plugins\ComLogicommerceMagicfront\Core\RegisteredUserProfile\RegisteredUserProfileService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Core\RewardPoints\RewardPointsService;
use Plugins\ComLogicommerceMagicfront\Core\Rmas\RmasService;
use Plugins\ComLogicommerceMagicfront\Core\SalesAgentCustomers\SalesAgentCustomersService;
use Plugins\ComLogicommerceMagicfront\Core\SalesAgentSales\SalesAgentSalesService;
use Plugins\ComLogicommerceMagicfront\Core\SaveForLater\SaveForLaterService;
use Plugins\ComLogicommerceMagicfront\Core\ShoppingList\ShoppingListService;
use Plugins\ComLogicommerceMagicfront\Core\StockAlerts\StockAlertsService;
use Plugins\ComLogicommerceMagicfront\Core\Subscriptions\SubscriptionsService;
use Plugins\ComLogicommerceMagicfront\Core\VoucherCodes\VoucherCodesService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Enums\RouteType;

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
        'accountLogin'          => [AccountLoginService::class,          ['setAccountLogin' => null]],
        'accountLoginGoogle'    => [AccountLoginService::class,          ['setAccountLogin' => null]],
        'accountLoginFacebook'  => [AccountLoginService::class,          ['setAccountLogin' => null]],
        'addressBook'           => [AddressBookService::class,           ['setInvoicingAddresses' => 'invoicing', 'setShippingAddresses' => 'shipping']],
        'orderDetail'           => [OrderDetailService::class,           ['setOrderDetail' => null]],
        'orders'                => [OrdersService::class,                ['setOrders' => 'orders', 'setOrdersPagination' => 'pagination', 'setOrdersAccountNames' => 'accountNames']],
        'shoppingList'          => [ShoppingListService::class,          ['setShoppingListRows' => 'rows', 'setShoppingLists' => 'lists']],
        'rewardPoints'          => [RewardPointsService::class,          ['setRewardPoints' => null]],
        'checkoutRewardPoints'  => [RewardPointsService::class,          ['setRewardPoints' => null]],
        'rmas'                  => [RmasService::class,                  ['setRmas' => null]],
        'saveForLater'          => [SaveForLaterService::class,          ['setSaveForLater' => 'rows']],
        'stockAlerts'           => [StockAlertsService::class,           ['setStockAlerts' => null]],
        'subscriptions'         => [SubscriptionsService::class,         ['setSubscriptions' => null]],
        'paymentCards'          => [PaymentCardsService::class,          ['setPaymentCards' => null]],
        'salesAgentCustomers'   => [SalesAgentCustomersService::class,   ['setSalesAgentCustomers' => null]],
        'salesAgentSales'       => [SalesAgentSalesService::class,       ['setSalesAgentSales' => null]],
        'saveForLaterSlider'    => [SaveForLaterService::class,          ['setSaveForLaterRows' => null]],
        'checkoutLinkeds'       => [CheckoutLinkedsService::class,        ['setCheckoutLinkeds' => null]],
        'checkoutSelectableGifts' => [CheckoutSelectableGiftsService::class, ['setCheckoutSelectableGifts' => null]],
        'checkoutPayments'      => [CheckoutPaymentsService::class,       ['setCheckoutPayments' => null]],
        'checkoutShippings'     => [CheckoutShippingsService::class,      ['setCheckoutShippings' => null]],
        'checkoutBasket'        => [CheckoutBasketService::class,         ['setCheckoutBasket' => null]],
        'checkoutWarnings'      => [CheckoutWarningsService::class,       ['setCheckoutWarnings' => null]],
        'checkoutPlaceOrder'    => [CheckoutPlaceOrderService::class,     ['setCheckoutPlaceOrder' => null]],
        'voucherCodes'          => [VoucherCodesService::class,          ['setVoucherCodes' => null]],
        'registeredUserData'    => [RegisteredUserDataService::class,    ['setRegisteredUserData' => null]],
        'registeredUserProfile' => [RegisteredUserProfileService::class, ['setRegisteredUserProfile' => null]],
    ];

    /**
     * Types whose data hangs off the BASKET, not off a logged-in account: they must be fetched for
     * anonymous visitors too (checkout is frequently anonymous). Every other type in {@see self::MAP}
     * reads the account API and is skipped without a session — see {@see self::requiresAccount()}.
     * `accountLogin` sits here for the opposite reason: it is the ANONYMOUS face, so gating it on a
     * session would starve it exactly when it renders. Its service reads the oauth plugin list, never
     * the account API.
     *
     * @var string[]
     */
    private const BASKET_SCOPED = ['accountLogin', 'accountLoginGoogle', 'accountLoginFacebook', 'checkoutLinkeds', 'checkoutSelectableGifts', 'checkoutPayments', 'checkoutShippings', 'checkoutBasket', 'checkoutWarnings', 'checkoutPlaceOrder'];

    /**
     * Types the full-page render must NOT fetch: the checkout OSC additional-content modules. The store
     * ships them as empty shells and fills them after load, so blocking the checkout document on three
     * extra API calls would be slower than the store for no visible gain. Their widgets carry
     * `data-mff-defer` and pull their own content through `widgetContent` once the page is interactive
     * — that path calls {@see self::fetchInto()} directly, so the data still arrives.
     *
     * @var string[]
     */
    private const DEFERRED = ['checkoutLinkeds', 'checkoutSelectableGifts', 'saveForLaterSlider', 'checkoutRewardPoints', 'checkoutPayments', 'checkoutShippings'];

    /**
     * Whether $type is fetched lazily by the widget itself instead of by the page render.
     *
     * Deferring is a ONE-PAGE-checkout optimisation: there the store also ships those modules empty and
     * fills them after load, and the OSC engine binds them again on every refresh. The native step
     * routes have no OSC engine — {@see \LC\CheckoutForm} binds once, at page load, by markup
     * (`.basketSelectorPaymentInput`, `input.shippingTypeSelector`, …), so a module that arrives later
     * over AJAX is never bound and its radios do nothing. On a step route nothing is deferred.
     */
    public static function isDeferred(string $type): bool {
        if (CheckoutRoute::current() !== RouteType::CHECKOUT) {
            return false;
        }
        return in_array($type, self::DEFERRED, true);
    }

    /** Whether $type needs a logged-in account before its service may run. */
    public static function requiresAccount(string $type): bool {
        return !in_array($type, self::BASKET_SCOPED, true);
    }

    /**
     * Pieces that paint part of a data widget's data without being that widget: the account Summary's (E22). A panel
     * can hold the piece and not the widget, so the piece asks for the data itself, through the data widget's service
     * and onto the same Page setters (the piece reads `page.orders`, `page.shippingAddresses`… like the widget).
     * When the widget is in the same panel, its fetch is the one used: one call, not two.
     *
     * piece type => [data type, method of the data type's service that fetches what the piece needs]. The method
     * returns null when the API did not answer, and the piece then gets nothing, not even the mark in
     * `page.loadedData`: the piece paints nothing, instead of an empty list that would say «no orders yet».
     *
     * @var array
     */
    private const PIECES = [
        'lastOrder'       => ['orders',       'fetchLatest'],
        'mainAddress'     => ['addressBook',  'fetchForPiece'],
        'pointsSummary'   => ['rewardPoints', 'fetchForPiece'],
        'vouchersSummary' => ['voucherCodes', 'fetchForPiece'],
    ];

    /** @return string[] every data-widget type this registry can fetch. */
    public static function types(): array {
        return array_keys(self::MAP);
    }

    /** @return string[] every piece type that reads a data widget's data. */
    public static function pieceTypes(): array {
        return array_keys(self::PIECES);
    }

    /** @return string[] the piece types that read $type's data. */
    public static function piecesReading(string $type): array {
        return array_keys(array_filter(self::PIECES, static fn(array $piece): bool => $piece[0] === $type));
    }

    /** The data type a piece reads, or null when $type is not a piece. */
    public static function dataTypeOfPiece(string $type): ?string {
        return self::PIECES[$type][0] ?? null;
    }

    /** A data widget or a piece that reads one. */
    public static function handles(string $type): bool {
        return isset(self::MAP[$type]) || isset(self::PIECES[$type]);
    }

    /**
     * Fetch $type's data for the current request via its Service and attach it onto every page in $pages
     * (recursively). $type is a data widget, or a piece, which fetches its data widget's data the way the piece
     * needs it. No-op for an unknown type or a null collection.
     *
     * @return array the Page setter assignments attached ([] when nothing was fetched, or when a piece's fetch got no
     *               answer from the API), to reuse with {@see attach()}
     */
    public static function fetchInto(?ElementCollection $pages, string $type, ?PluginPage $widget): array {
        if ($pages === null || !self::handles($type)) {
            return [];
        }
        [$dataType, $method] = self::PIECES[$type] ?? [$type, 'fetchForWidget'];
        [$serviceClass, $setterMap] = self::MAP[$dataType];
        $data = (new $serviceClass())->{$method}($widget);
        if ($data === null) {
            return [];
        }
        $assignments = [];
        foreach ($setterMap as $setter => $key) {
            $assignments[$setter] = $key === null ? $data : ($data[$key] ?? []);
        }
        // What tells a piece «loaded» from «not loaded»: the getters above always exist for Twig.
        $assignments['addLoadedData'] = $dataType;
        self::attach($pages, $assignments);
        return $assignments;
    }

    /** Attach data already fetched (by {@see fetchInto()}) onto every page in $pages, recursively. */
    public static function attach(ElementCollection $pages, array $assignments): void {
        if ($assignments !== []) {
            PageRelationResolver::attachWidgetData($pages, $assignments);
        }
    }
}
