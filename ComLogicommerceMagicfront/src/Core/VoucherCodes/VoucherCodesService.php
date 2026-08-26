<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\VoucherCodes;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Voucher-codes data CARRIER for the `voucherCodes` widget. Pure transport: fetches the account's
 * redeemable vouchers ({@see \FWK\Services\UserService::getVouchers} → ACCOUNTS_VOUCHERS) and hands
 * the COMPLETE raw items to the widget — `toArray()['items']` verbatim. The widget owns display
 * (code / available balance / expiration date), mirroring the FWK redeemVouchers macro. Read-only;
 * the store list is not paginated.
 *
 * `items` are `Voucher::toArray()` — each carries `code`, `availableBalance` (float) and
 * `expirationDate` (Date → originalFormat string).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\VoucherCodes
 */
class VoucherCodesService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $collection = Loader::service(Services::USER)->getVouchers();
        $items      = $collection instanceof ElementCollection ? $collection->toArray()['items'] : [];
        return ['items' => $items];
    }
}
