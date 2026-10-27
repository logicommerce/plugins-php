<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\SalesAgentCustomers;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Services\Parameters\Groups\User\SalesAgentCustomersParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Sales-agent customers data CARRIER for the `salesAgentCustomers` widget. Mirrors
 * {@see \FWK\Controllers\Account\RegisteredUserSalesAgentCustomersController}: reads the request
 * filter params through {@see RequestQuery} (q / fromDate / toDate / includeSubordinates / page — a
 * value LogiCommerce would reject is ignored, and dates default to the last 7 days when absent or
 * invalid, as the FWK controller does), calls {@see \FWK\Services\AccountService::getSalesAgentCustomers}
 * (Resource ACCOUNT_SALES_AGENT_CUSTOMERS, paginable) and hands the COMPLETE raw items verbatim.
 * Pure transport — `toArray()['items']`, no reshaping. Shared by the full-page render
 * ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider}) and the widget's
 * own AJAX endpoint (via {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}). `salesAgentId` + the resolved
 * request echo travel alongside so the widget can build the LC-owned action payloads and pre-fill
 * the filter form.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\SalesAgentCustomers
 */
class SalesAgentCustomersService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $q                   = $this->resolveQ();
        $includeSubordinates = $this->resolveIncludeSubordinates();
        $fromDate            = $this->resolveFromDate();
        $toDate              = $this->resolveToDate();
        $params              = $this->buildParams($q, $includeSubordinates, $fromDate, $toDate, PaginationHelper::perPage($widget));

        $collection = Loader::service(Services::ACCOUNT)->getSalesAgentCustomers($params);
        $items      = $collection instanceof ElementCollection ? $collection->toArray()['items'] : [];
        $pagination = $collection instanceof ElementCollection ? $collection->getPagination() : null;

        return [
            'items'        => $items,
            'pagination'   => $pagination !== null
                ? ['page' => $pagination->getPage(), 'totalPages' => $pagination->getTotalPages()]
                : ['page' => 1, 'totalPages' => 1],
            'salesAgentId' => Session::getInstance()->getBasket()->getRegisteredUser()?->getSalesAgentId() ?? 0,
            'request'      => [
                'q'                   => $q,
                'includeSubordinates' => $includeSubordinates,
                'fromDate'            => $fromDate->format('Y-m-d'),
                'toDate'              => $toDate->format('Y-m-d'),
                'modalFromDate'       => (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->modify('-10 years')->format('Y-m-d'),
                'modalToDate'         => (new \DateTime('now', new \DateTimeZone(date_default_timezone_get())))->format('Y-m-d'),
            ],
        ];
    }

    private function buildParams(string $q, bool $includeSubordinates, \DateTime $fromDate, \DateTime $toDate, ?int $perPage): SalesAgentCustomersParametersGroup {
        $params = new SalesAgentCustomersParametersGroup();
        if ($q !== '') {
            $params->setQ($q);
        }
        $params->setFromDate($fromDate);
        $params->setToDate($toDate);
        $params->setIncludeSubordinates($includeSubordinates);
        if ($perPage !== null && $perPage > 0) {
            $params->setPerPage($perPage);
        }
        $page = RequestQuery::value(Parameters::PAGE, FilterInputFactory::getPaginableItemsParameter()[Parameters::PAGE]);
        if ($page !== null) {
            $params->setPage((int) $page);
        }
        return $params;
    }

    /** The requested search, or '' when absent. */
    private function resolveQ(): string {
        return RequestQuery::value(Parameters::Q, RequestQuery::text()) ?? '';
    }

    /** Whether the subordinates' customers were asked for; no when absent or not a yes/no. */
    private function resolveIncludeSubordinates(): bool {
        return filter_var(RequestQuery::value(Parameters::INCLUDE_SUBORDINATES, RequestQuery::flag()), FILTER_VALIDATE_BOOLEAN);
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
