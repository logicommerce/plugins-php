<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Core\SaveForLater\SaveForLaterService;
use SDK\Core\Dtos\ElementCollection;

/**
 * Attaches MagicFront's sample customer (`GET /samples/account`, #4502) to the account page in the editor canvas: the
 * same `page.*` variables and setters the store fills from the API ({@see
 * \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}), each data type marked in `page.loadedData`,
 * and who is looking in `page.accountViewer`. An account piece then reads the same variable in the canvas as in the
 * store, in the situation the editor chose in «Who is looking?».
 *
 * A data type the sample does not carry (a visit without session carries none) is neither set nor marked: the piece
 * sees «not loaded». An empty account carries every list EMPTY, so the piece sees «loaded and empty».
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class AccountSample {

    /** data type (the data widget's, as in `page.loadedData`) => [Page setter => key of the sample] */
    public const DATA = [
        'orders'       => ['setOrders' => 'orders', 'setOrdersPagination' => 'ordersPagination', 'setOrdersAccountNames' => 'ordersAccountNames'],
        'addressBook'  => ['setShippingAddresses' => 'shippingAddresses', 'setInvoicingAddresses' => 'invoicingAddresses'],
        'rewardPoints' => ['setRewardPoints' => 'rewardPoints'],
        'voucherCodes' => ['setVoucherCodes' => 'voucherCodes'],
        'saveForLater' => ['setSaveForLater' => 'saveForLater'],
    ];

    /** Who is looking: `page.accountViewer`, marked as 'viewer' in `page.loadedData` (null = without session). */
    public const VIEWER = 'viewer';

    /**
     * The setter batches for $sample, one per data type it carries whole, each with its `page.loadedData` mark.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function assignments(array $sample): array {
        $batches = [];
        foreach (self::DATA as $type => $setters) {
            $batch = [];
            foreach ($setters as $setter => $key) {
                if (!array_key_exists($key, $sample) || !is_array($sample[$key])) {
                    continue 2;
                }
                $batch[$setter] = $sample[$key];
            }
            $batch['addLoadedData'] = $type;
            $batches[] = $batch;
        }
        if (array_key_exists(self::VIEWER, $sample)) {
            $viewer = $sample[self::VIEWER];
            $batches[] = ['setAccountViewer' => is_array($viewer) ? $viewer : null, 'addLoadedData' => self::VIEWER];
        }
        return $batches;
    }

    /**
     * Attaches $sample onto every page in $pages, recursively. A null collection or an empty sample does nothing.
     *
     * The sample's «saved for later» is the raw collection the API answers ({items, products, bundles}): it is joined
     * here as the store joins it ({@see SaveForLaterService::richRows()}), so the widget reads the same objects in the
     * canvas as in the store (#6621).
     */
    public static function attach(?ElementCollection $pages, array $sample): void {
        if ($pages === null) {
            return;
        }
        foreach (self::assignments($sample) as $batch) {
            if (array_key_exists('setSaveForLater', $batch)) {
                $batch['setSaveForLater'] = (new SaveForLaterService())->richRows($batch['setSaveForLater']);
            }
            PageRelationResolver::attachWidgetData($pages, $batch);
        }
    }
}
