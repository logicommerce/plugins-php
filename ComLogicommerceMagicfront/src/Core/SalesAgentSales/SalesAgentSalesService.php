<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\SalesAgentSales;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\SalesAgentSales as SalesAgentSalesCollection;
use SDK\Services\Parameters\Groups\User\SalesAgentSalesParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Sales-agent sales data CARRIER for the `salesAgentSales` widget. Mirrors
 * {@see \FWK\Controllers\Account\RegisteredUserSalesAgentSalesController}: reads the request date
 * range through {@see RequestQuery} (fromDate / toDate / page — dates default to the last 7 days when
 * absent or invalid) and calls
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
        $params   = $this->buildParams($fromDate, $toDate, PaginationHelper::perPage($widget));

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

    private function buildParams(\DateTime $fromDate, \DateTime $toDate, ?int $perPage): SalesAgentSalesParametersGroup {
        $params = new SalesAgentSalesParametersGroup();
        $params->setFromDate($fromDate);
        $params->setToDate($toDate);
        if ($perPage !== null && $perPage > 0) {
            $params->setPerPage($perPage);
        }
        $page = RequestQuery::value(Parameters::PAGE, FilterInputFactory::getPaginableItemsParameter()[Parameters::PAGE]);
        if ($page !== null) {
            $params->setPage((int) $page);
        }
        return $params;
    }

    /** The requested start date, or seven days ago when absent or not a date. */
    private function resolveFromDate(): \DateTime {
        $fromDate = RequestQuery::date(Parameters::FROM_DATE)
            ?? (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->modify('-7 days');
        return $fromDate->setTime(0, 0, 0);
    }

    /** The requested end date, or today when absent or not a date. */
    private function resolveToDate(): \DateTime {
        $toDate = RequestQuery::date(Parameters::TO_DATE) ?? new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
        return $toDate->setTime(23, 59, 59);
    }
}
