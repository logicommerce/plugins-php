<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\SalesAgentSales;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\SalesAgentSales as SalesAgentSalesCollection;
use SDK\Services\Parameters\Groups\User\SalesAgentSalesParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Sales-agent sales data CARRIER for the `salesAgentSales` widget. Mirrors
 * {@see \FWK\Controllers\Account\RegisteredUserSalesAgentSalesController}: reads the request date
 * range (fromDate / toDate / page — dates default to the last 7 days when absent) and calls
 * {@see \FWK\Services\AccountService::getSalesAgentSales} (Resource ACCOUNT_SALES_AGENT_SALES,
 * paginable). The result is a {@see SalesAgentSalesCollection} carrying BOTH the paged sale rows
 * (`items`) AND the summary totals (`salesAgentTotals`). Pure transport — raw `toArray()['items']`
 * + the totals verbatim, no reshaping. Shared by the full-page render and the widget's own AJAX
 * endpoint (via {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\SalesAgentSales
 */
class SalesAgentSalesService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $fromDate = $this->resolveFromDate();
        $toDate   = $this->resolveToDate();

        $params = new SalesAgentSalesParametersGroup();
        $params->setFromDate($fromDate);
        $params->setToDate($toDate);
        $perPage = PaginationHelper::perPage($widget);
        if ($perPage !== null && $perPage > 0) {
            $params->setPerPage($perPage);
        }
        if (isset($_GET['page']) && is_numeric($_GET['page'])) {
            $params->setPage((int) $_GET['page']);
        }

        $collection = Loader::service(Services::ACCOUNT)->getSalesAgentSales($params);
        $items      = $collection instanceof SalesAgentSalesCollection ? $collection->toArray()['items'] : [];
        $totals     = $collection instanceof SalesAgentSalesCollection && $collection->getSalesAgentTotals() !== null
            ? $collection->getSalesAgentTotals()->toArray()
            : [];
        $pagination = $collection instanceof SalesAgentSalesCollection ? $collection->getPagination() : null;

        return [
            'items'      => $items,
            'totals'     => $totals,
            'pagination' => $pagination !== null
                ? ['page' => $pagination->getPage(), 'totalPages' => $pagination->getTotalPages()]
                : ['page' => 1, 'totalPages' => 1],
            'request'    => [
                'fromDate' => $fromDate->format('Y-m-d'),
                'toDate'   => $toDate->format('Y-m-d'),
            ],
        ];
    }

    private function resolveFromDate(): \DateTime {
        if (!empty($_GET['fromDate'])) {
            return (new \DateTime((string) $_GET['fromDate']))->setTime(0, 0, 0);
        }
        return (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->modify('-7 days')->setTime(0, 0, 0);
    }

    private function resolveToDate(): \DateTime {
        if (!empty($_GET['toDate'])) {
            return (new \DateTime((string) $_GET['toDate']))->setTime(23, 59, 59);
        }
        return (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->setTime(23, 59, 59);
    }
}
