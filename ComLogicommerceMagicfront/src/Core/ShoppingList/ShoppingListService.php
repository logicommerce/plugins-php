<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\ShoppingList;

use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\ShoppingListRowsCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Dtos\User\ShoppingList;
use SDK\Services\Parameters\Groups\User\ShoppingListRowsParametersGroup;
use SDK\Services\Parameters\Groups\User\ShoppingListsParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Shopping-list (Favoritos) data CARRIER for the `shoppingList` widget. Pure transport: fetches the
 * account's shopping lists and the default list's rows (for the current $_GET sort/search) and hands
 * the COMPLETE raw API structures to the widget — `collection->toArray()` verbatim, no field-picking,
 * no reshaping. The widget owns every display rule (pick the default list, the FWK RichShoppingListRows
 * product/bundle join, date formatting, row-type dispatch); see {@see \FWK\Core\Controllers\Traits\RichShoppingListRows}
 * for the rules replicated in the widget Twig.
 *
 * `rows` is a ShoppingListRowsCollection::toArray() → `{items, products, bundles, pagination}` (items
 * carry `reference.{type,id,combinationData,options}` + comment/quantity/importance/priority/addedDate).
 * `lists` is the shopping-lists collection items (each list carries name/description/defaultOne/
 * keepPurchasedItems/priority). Shared by the full-page render
 * ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider}) and the widget's AJAX
 * endpoint ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler}
 * via {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\ShoppingList
 */
class ShoppingListService implements WidgetDataService {

    private const KEY = 'mffShoppingListRows';

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $settings    = $widget !== null ? $widget->getModuleSettings() : [];
        $defaultSort = !empty($settings['defaultSort']) ? (string) $settings['defaultSort'] : null;
        return $this->fetch($defaultSort);
    }

    /**
     * @return array
     */
    private function fetch(?string $defaultSort): array {
        $listsCollection = Loader::service(Services::USER)->getShoppingLists(new ShoppingListsParametersGroup());
        $lists           = $listsCollection instanceof ElementCollection ? $listsCollection->toArray()['items'] : [];
        $listId          = $this->resolveListId($listsCollection);

        $rows = ['items' => [], 'products' => [], 'bundles' => [], 'pagination' => null];
        if ($listId > 0) {
            $requests = new BatchRequests();
            Loader::service(Services::USER)->addGetShoppingListRows($requests, self::KEY, $listId, $this->buildParams($defaultSort));
            $collection = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
            if ($collection instanceof ShoppingListRowsCollection) {
                $rows = $collection->toArray();
            }
        }

        return ['lists' => $lists, 'rows' => $rows];
    }

    /**
     * The list to fetch rows for: the requested `id` ($_GET) when it is one of the account's lists,
     * else the default list, else the first, else 0. Mirrors FWK
     * {@see \FWK\Core\Controllers\FiltrableShoppingListRowsTrait} (shoppingListRowsFilter.id).
     */
    private function resolveListId(?ElementCollection $lists): int {
        if (!$lists instanceof ElementCollection) {
            return 0;
        }
        $requested = isset($_GET[Parameters::ID]) ? (int) $_GET[Parameters::ID] : 0;
        $default   = 0;
        $first     = 0;
        $hasRequested = false;
        foreach ($lists->getItems() as $list) {
            if (!$list instanceof ShoppingList) {
                continue;
            }
            if ($requested > 0 && $list->getId() === $requested) {
                $hasRequested = true;
            }
            if ($default === 0 && $list->getDefaultOne()) {
                $default = $list->getId();
            }
            if ($first === 0) {
                $first = $list->getId();
            }
        }
        if ($hasRequested) {
            return $requested;
        }
        return $default !== 0 ? $default : $first;
    }

    private function buildParams(?string $defaultSort): ShoppingListRowsParametersGroup {
        $params = new ShoppingListRowsParametersGroup();
        if (!empty($_GET[Parameters::SORT])) {
            $params->setSort(strtoupper((string) $_GET[Parameters::SORT]));
        } elseif (!empty($defaultSort)) {
            $params->setSort(strtoupper($defaultSort));
        }
        if (!empty($_GET[Parameters::Q])) {
            $params->setQ((string) $_GET[Parameters::Q]);
        }
        return $params;
    }
}
