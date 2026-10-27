<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Orders;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use SDK\Core\Dtos\AccountOrderCollection;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Enums\AccountKey;
use SDK\Enums\OrderSort;
use SDK\Enums\OrderStatus;
use SDK\Services\Parameters\Groups\Account\AccountOrderParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Order-history data for the `orders` widget. Fetches the logged-in account's orders for the
 * current request's filter/sort/page params (read through {@see RequestQuery}: a value LogiCommerce
 * would reject is ignored) and maps them to the flat `page.orders` shape the widget renders. Shared by the full-page render ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider})
 * and the widget's own AJAX endpoint ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler} via {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}).
 *
 * FWK/SDK-side (not in the docker/PHAR subset): uses the FWK Loader + SDK account service.
 * The caller gates on a logged-in session; the widget owns display rules (status label, date
 * format, pagination/filter markup) — this only transports raw API values.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Orders
 */
class OrdersService implements WidgetDataService {

    private const KEY = 'mffOrders';

    /**
     * Fetch using the query settings the placed widget carries. `defaultSort` is the orders widget's own
     * moduleSetting; `perPage` (the API page size) now lives on the embedded `pagination` slot widget —
     * the single source for pagination config — read from the orders widget's slot child.
     *
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $settings    = $widget !== null ? $widget->getModuleSettings() : [];
        $defaultSort = !empty($settings['defaultSort']) ? (string) $settings['defaultSort'] : null;
        return $this->fetch(PaginationHelper::perPage($widget), $defaultSort);
    }

    /**
     * The account's most recent order, for a piece that shows only that one (`lastOrder`, the account Summary's, E22):
     * one order sorted by date, newest first, asked to the API. The list's page, sort and filters in the URL belong to
     * the orders list and do not apply here. Same payload shape as {@see fetchForWidget()}, or null when the API did
     * not answer: the piece then gets no `page.orders` and paints nothing, never a false «no orders yet».
     */
    public function fetchLatest(?PluginPage $widget): ?array {
        $params = new AccountOrderParametersGroup();
        $params->setPerPage(1);
        $params->setSort(OrderSort::DATE . '.' . OrderSort::SORT_DIRECTION_DESC);
        return $this->send($params);
    }

    /**
     * @return array
     */
    private function fetch(?int $perPage, ?string $defaultSort): array {
        return $this->send($this->buildParams($perPage, $defaultSort))
            ?? ['orders' => [], 'accountNames' => [], 'pagination' => ['page' => 1, 'totalPages' => 1]];
    }

    /**
     * @return array|null the orders, or null when the API did not answer
     */
    private function send(AccountOrderParametersGroup $params): ?array {
        $requests = new BatchRequests();
        Loader::service(Services::ACCOUNT)->addGetOrders($requests, self::KEY, AccountKey::USED, $params);
        $collection = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
        if (!$collection instanceof ElementCollection) {
            return null;
        }
        $pagination = $collection->getPagination();
        return [
            'orders'       => json_decode(json_encode($collection->getItems()), true),
            'accountNames' => $collection instanceof AccountOrderCollection ? $this->accountNames($collection) : [],
            'pagination'   => $pagination !== null
                ? ['page' => $pagination->getPage(), 'totalPages' => $pagination->getTotalPages()]
                : ['page' => 1, 'totalPages' => 1],
        ];
    }

    private function buildParams(?int $perPage, ?string $defaultSort): AccountOrderParametersGroup {
        $params = new AccountOrderParametersGroup();
        if ($perPage !== null && $perPage > 0) {
            $params->setPerPage($perPage);
        }
        $page = RequestQuery::value(Parameters::PAGE, FilterInputFactory::getPaginableItemsParameter()[Parameters::PAGE]);
        if ($page !== null) {
            $params->setPage((int) $page);
        }
        $sort = RequestQuery::value(Parameters::SORT, FilterInputFactory::getSortableItemsParameters('OrderSort')[Parameters::SORT]);
        if ($sort !== null) {
            $params->setSort($sort);
        } elseif (!empty($defaultSort)) {
            $params->setSort(strtoupper($defaultSort));
        }
        $addedFrom = RequestQuery::date(Parameters::ADDED_FROM);
        if ($addedFrom !== null) {
            $params->setAddedFrom($addedFrom->setTime(0, 0, 0));
        }
        $addedTo = RequestQuery::date(Parameters::ADDED_TO);
        if ($addedTo !== null) {
            $params->setAddedTo($addedTo->setTime(23, 59, 59));
        }
        $onlyCreatedByMe = RequestQuery::value(Parameters::ONLY_CREATED_BY_ME, RequestQuery::flag());
        if ($onlyCreatedByMe !== null) {
            $params->setOnlyCreatedByMe(filter_var($onlyCreatedByMe, FILTER_VALIDATE_BOOLEAN));
        }
        $includeSubCompanyStructure = RequestQuery::value(Parameters::INCLUDE_SUBCOMPANY_STRUCTURE, RequestQuery::flag());
        if ($includeSubCompanyStructure !== null) {
            $params->setIncludeSubCompanyStructure(filter_var($includeSubCompanyStructure, FILTER_VALIDATE_BOOLEAN));
        }
        $statusIdList = RequestQuery::value(
            Parameters::STATUS_ID_LIST,
            // Status names, as the order widgets send them and the FWK's account form declares them.
            RequestQuery::oneOf(OrderStatus::getServiceValidStatuses())
        );
        if ($statusIdList !== null) {
            $params->setStatusIdList($statusIdList);
        }
        return $params;
    }

    /** @return array owner account id → name (company multi-account orders). */
    private function accountNames(AccountOrderCollection $collection): array {
        $names = [];
        foreach ($collection->getAccounts() as $account) {
            $names[$account->getId()] = $account->getName();
        }
        return $names;
    }
}
