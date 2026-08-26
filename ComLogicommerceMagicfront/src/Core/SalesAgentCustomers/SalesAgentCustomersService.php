<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\SalesAgentCustomers;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Services\Parameters\Groups\User\SalesAgentCustomersParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Sales-agent customers data CARRIER for the `salesAgentCustomers` widget. Mirrors
 * {@see \FWK\Controllers\Account\RegisteredUserSalesAgentCustomersController}: reads the request
 * filter params (q / fromDate / toDate / includeSubordinates / page — dates default to the last 7
 * days when absent, as the FWK controller does), calls {@see \FWK\Services\AccountService::getSalesAgentCustomers}
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
        $q                   = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $includeSubordinates = isset($_GET['includeSubordinates']) && $_GET['includeSubordinates'] !== '' ? (bool) (int) $_GET['includeSubordinates'] : false;
        $fromDate            = $this->resolveFromDate();
        $toDate              = $this->resolveToDate();

        $params = new SalesAgentCustomersParametersGroup();
        if ($q !== '') {
            $params->setQ($q);
        }
        $params->setFromDate($fromDate);
        $params->setToDate($toDate);
        $params->setIncludeSubordinates($includeSubordinates);
        $perPage = PaginationHelper::perPage($widget);
        if ($perPage !== null && $perPage > 0) {
            $params->setPerPage($perPage);
        }
        if (isset($_GET['page']) && is_numeric($_GET['page'])) {
            $params->setPage((int) $_GET['page']);
        }

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
