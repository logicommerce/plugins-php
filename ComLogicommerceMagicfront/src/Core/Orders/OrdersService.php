<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Orders;

use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use SDK\Core\Dtos\AccountOrderCollection;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Enums\AccountKey;
use SDK\Services\Parameters\Groups\Account\AccountOrderParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Order-history data for the `orders` widget. Fetches the logged-in account's orders for the
 * current request's filter/sort/page params ($_GET) and maps them to the flat `page.orders` shape
 * the widget renders. Shared by the full-page render ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider})
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
     * @return array
     */
    private function fetch(?int $perPage, ?string $defaultSort): array {
        $requests = new BatchRequests();
        Loader::service(Services::ACCOUNT)->addGetOrders($requests, self::KEY, AccountKey::USED, $this->buildParams($perPage, $defaultSort));
        $collection = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
        if (!$collection instanceof ElementCollection) {
            return ['orders' => [], 'accountNames' => [], 'pagination' => ['page' => 1, 'totalPages' => 1]];
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
        if (isset($_GET[Parameters::PAGE]) && is_numeric($_GET[Parameters::PAGE])) {
            $params->setPage((int) $_GET[Parameters::PAGE]);
        }
        if (!empty($_GET[Parameters::SORT])) {
            $params->setSort(strtoupper((string) $_GET[Parameters::SORT]));
        } elseif (!empty($defaultSort)) {
            $params->setSort(strtoupper($defaultSort));
        }
        if (!empty($_GET[Parameters::ADDED_FROM])) {
            $params->setAddedFrom((new \DateTime((string) $_GET[Parameters::ADDED_FROM]))->setTime(0, 0, 0));
        }
        if (!empty($_GET[Parameters::ADDED_TO])) {
            $params->setAddedTo((new \DateTime((string) $_GET[Parameters::ADDED_TO]))->setTime(23, 59, 59));
        }
        if (isset($_GET[Parameters::ONLY_CREATED_BY_ME]) && $_GET[Parameters::ONLY_CREATED_BY_ME] !== '') {
            $params->setOnlyCreatedByMe((bool) (int) $_GET[Parameters::ONLY_CREATED_BY_ME]);
        }
        if (isset($_GET[Parameters::INCLUDE_SUBCOMPANY_STRUCTURE]) && $_GET[Parameters::INCLUDE_SUBCOMPANY_STRUCTURE] !== '') {
            $params->setIncludeSubCompanyStructure((bool) (int) $_GET[Parameters::INCLUDE_SUBCOMPANY_STRUCTURE]);
        }
        if (!empty($_GET[Parameters::STATUS_ID_LIST])) {
            $params->setStatusIdList((string) $_GET[Parameters::STATUS_ID_LIST]);
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
