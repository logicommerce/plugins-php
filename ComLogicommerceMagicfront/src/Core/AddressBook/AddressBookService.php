<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\AddressBook;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;

/**
 * Address-book data CARRIER for the `addressBook` widget: fetches the account's invoicing + shipping
 * addresses FRESH from the account service each render (mirrors the native AddressBookController, which
 * addGet*Addresses per page load) and attaches them as `page.invoicingAddresses`/`page.shippingAddresses`.
 * Reading the session basket account instead renders a STALE list — the session cache is NOT refreshed
 * after a set-default / add / edit / delete write, so the change would revert on reload. Same
 * AccountInvoicingAddress/AccountShippingAddress DTO shape as the session account, so the widget's card
 * fields are unchanged.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\AddressBook
 */
class AddressBookService implements WidgetDataService {

    public function fetchForWidget(?PluginPage $widget): array {
        $account = Loader::service(Services::ACCOUNT);
        return [
            'invoicing' => $this->items($account->getInvoicingAddresses()),
            'shipping'  => $this->items($account->getShippingAddresses()),
        ];
    }

    /**
     * @return array the collection's items serialized verbatim, or [] when absent
     */
    private function items(mixed $collection): array {
        if (!$collection instanceof ElementCollection) {
            return [];
        }
        return json_decode(json_encode($collection->getItems()), true);
    }
}
