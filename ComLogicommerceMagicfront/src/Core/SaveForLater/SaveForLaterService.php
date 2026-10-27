<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\SaveForLater;

use FWK\Core\Controllers\Traits\RichSaveForLaterRows;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\SaveForLaterListRowsCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;

/**
 * «Saved for later» data CARRIER for the account's `saveForLater` widget (E22). LogiCommerce shows these rows only in
 * the basket ({@see \FWK\Controllers\Checkout\Internal\OscSaveForLaterController}); this asks for them the same way
 * ({@see \SDK\Services\UserService::addGetSaveForLaterListRows}, no parameters) and joins each row with what it saved
 * with the base theme's own {@see RichSaveForLaterRows}, so the widget gets exactly what the basket gets.
 *
 * `page.saveForLater` is the list of rows ([] when there is none): each a {@see \FWK\Dtos\User\SaveForLaterListRow}
 * — `id`, `quantity`, `addedDate` (SDK Date), `reference` (what was saved, raw: its type, id, combinationData and
 * options, attachments included) and `item`: the SDK Product with the row's combinationData, or the FWK BundleGrouping
 * with it and its `products` (by product id; their order is the bundle's `items`). The widget picks what it shows and
 * words it: no LogiCommerce label and no date format are applied here (#6621).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\SaveForLater
 */
class SaveForLaterService implements WidgetDataService {

    use RichSaveForLaterRows;

    private const KEY = 'mffSaveForLater';

    /**
     * @return array|null ['rows' => …], or null when the API did not answer: nothing is attached, not even the mark in
     *                    `page.loadedData`, so the widget can tell «could not load» from «nothing saved».
     */
    public function fetchForWidget(?PluginPage $widget): ?array {
        $requests = new BatchRequests();
        Loader::service(Services::USER)->addGetSaveForLaterListRows($requests, self::KEY);
        $collection = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
        if (!$collection instanceof SaveForLaterListRowsCollection || $collection->getError() !== null) {
            return null;
        }
        return ['rows' => $this->richRows($collection->toArray())];
    }

    /**
     * The rows of a `SaveForLaterListRowsCollection::toArray()` ({items, products, bundles, …}) as the base theme joins
     * them. Also MagicFront's sample customer in the editor canvas ({@see \Plugins\ComLogicommerceMagicfront\Core\Resources\AccountSample}),
     * which carries the same shape, so the canvas and the store hand the widget the same objects.
     *
     * @return \FWK\Dtos\User\SaveForLaterListRow[]
     */
    public function richRows(array $collection): array {
        if (!is_array($collection['items'] ?? null) || $collection['items'] === []) {
            return [];
        }
        $rich = $this->getRichSaveForLaterRows(new SaveForLaterListRowsCollection(self::joinable($collection)));
        return $rich === null ? [] : array_values($rich->getItems());
    }

    /**
     * $collection without the rows the base theme's join cannot take: a row with no reference, a product or bundle that
     * did not come with the collection, or a bundle one of whose products did not. The join stops the whole page on any
     * of them (a method call on null); left out, the rest still paint. A product row without its combinationData gets
     * an empty one, which the join also reads unguarded.
     */
    public static function joinable(array $collection): array {
        $products = self::ids($collection['products'] ?? []);
        $bundles = [];
        foreach ($collection['bundles'] ?? [] as $bundle) {
            if (is_array($bundle) && isset($bundle['id'])) {
                $bundles[(int) $bundle['id']] = array_map(
                    static fn($item): int => is_array($item) ? (int) ($item['productId'] ?? 0) : 0,
                    is_array($bundle['items'] ?? null) ? $bundle['items'] : []
                );
            }
        }
        $items = [];
        foreach ($collection['items'] ?? [] as $row) {
            $reference = is_array($row) ? ($row['reference'] ?? null) : null;
            if (!is_array($reference)) {
                continue;
            }
            $id = (int) ($reference['id'] ?? 0);
            if (($reference['type'] ?? '') === 'BUNDLE') {
                if (!isset($bundles[$id]) || array_diff($bundles[$id], $products) !== []) {
                    continue;
                }
            } else {
                if (!in_array($id, $products, true)) {
                    continue;
                }
                if (!is_array($reference['combinationData'] ?? null)) {
                    $row['reference']['combinationData'] = [];
                }
            }
            $items[] = $row;
        }
        $collection['items'] = $items;
        return $collection;
    }

    /** @return int[] the ids of $elements */
    private static function ids(array $elements): array {
        $ids = [];
        foreach ($elements as $element) {
            if (is_array($element) && isset($element['id'])) {
                $ids[] = (int) $element['id'];
            }
        }
        return $ids;
    }
}
