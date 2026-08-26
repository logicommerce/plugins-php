<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Rmas;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * RMA (return-requests) data CARRIER for the `rmas` widget. Pure transport: fetches the account's RMA list
 * ({@see \SDK\Services\UserService::addGetRMAs} → GET /accounts/used/rmas, NOT paginated) and hands the
 * COMPLETE raw items to the widget — `toArray()['items']` verbatim, no field-picking, no reshaping. The
 * widget owns every display rule (the FWK rmas macro markup, status-label map, date formatting, the
 * view/PDF/returns/correctiveInvoice action buttons + their popup modals); see the store
 * fwk/themes/core/macros/modes/bootstrap5/user/rmas.html.twig.
 *
 * `items` are `UserRMA::toArray()` — each carries id (the DOCUMENT id used by the action buttons /
 * `#popuprma{id}`), documentNumber (the human "Nº solicitud"), date, status (RMAStatus), substatus and
 * returns[] (each with id/documentNumber + correctiveInvoices[]).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Rmas
 */
class RmasService implements WidgetDataService {

    private const KEY = 'mffRmas';

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $requests = new BatchRequests();
        Loader::service(Services::USER)->addGetRMAs($requests, self::KEY);
        $result = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
        return ['items' => $result instanceof ElementCollection ? $result->toArray()['items'] : []];
    }
}
