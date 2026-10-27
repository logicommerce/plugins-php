<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\OrderDetail;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\OrderDetailUrl;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Dtos\Documents\Rich\RichOrder;
use SDK\Dtos\Documents\Transactions\Purchases\Order;

/**
 * The order the account's order detail section shows (`orderDetail` anchor widget, E22 phase 6-7, #4508): the order
 * `?mfOrderId=` names, for `page.orderDetail`.
 *
 * `page.orderDetail` is LogiCommerce's rich order (`OrderService::getRichOrder`, amounts already formatted in the
 * order's currency) as an array, plus:
 *   id (int), invoices (the order's invoices, `getOrderInvoices`) and rmas (its returns, `getOrderRMAs`), as the API
 *   gives them.
 *
 * Never somebody else's order, as the store's own order page (FWK `OrderController`, `getOrder` alone): the API answers
 * an order this user may not see with an error (403/404), and then nothing is attached and the widget says «not found».
 * The four questions go in one batch (#8997, points 2 and 3 of request 447). There is no allowReturn: only the order
 * lists give it (`AccountOrder`), and the store offers a return only from them.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\OrderDetail
 */
class OrderDetailService implements WidgetDataService {

    private const ORDER = 'mffOrderDetailOrder';

    private const RICH = 'mffOrderDetailRich';

    private const INVOICES = 'mffOrderDetailInvoices';

    private const RMAS = 'mffOrderDetailRmas';

    /**
     * @return array|null `page.orderDetail`, or null when there is no order to show: no `mfOrderId`, or the API did
     *                    not give it (not this user's, or no answer)
     */
    public function fetchForWidget(?PluginPage $widget): ?array {
        $id = OrderDetailUrl::requestedId(RequestQuery::value(OrderDetailUrl::PARAM, RequestQuery::positiveInt()));
        if ($id === null) {
            return null;
        }
        $requests = new BatchRequests();
        self::ask(Loader::service(Services::ORDER), $requests, $id);
        $answers = BatchService::getInstance()->send($requests);
        $order = $answers[self::ORDER] ?? null;
        $rich = $answers[self::RICH] ?? null;
        if (!$order instanceof Order || $order->getError() !== null || !$rich instanceof RichOrder || $rich->getError() !== null) {
            return null;
        }
        return self::payload($id, self::asArray($rich), self::items($answers[self::INVOICES] ?? null), self::items($answers[self::RMAS] ?? null));
    }

    /**
     * The order's four questions, into one batch: the order (which says whether this user may see it), the rich order,
     * its invoices and its returns.
     *
     * @param object $orders the order service (`Loader::service(Services::ORDER)`)
     */
    public static function ask(object $orders, BatchRequests $requests, int $id): void {
        $orders->addGetOrder($requests, self::ORDER, $id);
        $orders->addGetRichOrder($requests, self::RICH, $id);
        $orders->addGetOrderInvoices($requests, self::INVOICES, $id);
        $orders->addGetOrderRMAs($requests, self::RMAS, $id);
    }

    /**
     * `page.orderDetail`: the rich order with the keys the plugin adds, which win over any of the same name. Never
     * allowReturn, not even the rich order's own: «Request a return» is only offered from the lists.
     *
     * @param array $rich     the rich order as an array
     * @param array $invoices the order's invoices, each as an array
     * @param array $rmas     the order's returns, each as an array
     */
    public static function payload(int $id, array $rich, array $invoices, array $rmas): array {
        $payload = array_merge($rich, [
            'id'       => $id,
            'invoices' => array_values($invoices),
            'rmas'     => array_values($rmas),
        ]);
        unset($payload['allowReturn']);
        return $payload;
    }

    /** The items of an answered list as arrays; [] for a list the API did not give (the order still shows). */
    private static function items(mixed $collection): array {
        if (!$collection instanceof ElementCollection || $collection->getError() !== null) {
            return [];
        }
        return array_values(array_filter(array_map([self::class, 'asArray'], $collection->getItems())));
    }

    private static function asArray(mixed $element): array {
        $array = json_decode(json_encode($element), true);
        return is_array($array) ? $array : [];
    }
}
