<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\ShoppingList;

use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\ShoppingListRowsCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Dtos\User\ShoppingList;
use SDK\Services\Parameters\Groups\User\ShoppingListRowsParametersGroup;
use SDK\Services\Parameters\Groups\User\ShoppingListsParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PaginationHelper;

/**
 * Shopping-list (Favoritos) data CARRIER for the `shoppingList` widget. Pure transport: fetches the
 * account's shopping lists and the default list's rows (for the request's sort/search) and hands
 * the COMPLETE raw API structures to the widget — `collection->toArray()` verbatim, no field-picking,
 * no reshaping. The widget owns every display rule (pick the default list, the FWK RichShoppingListRows
 * product/bundle join, date formatting, row-type dispatch); see {@see \FWK\Core\Controllers\Traits\RichShoppingListRows}
 * for the rules replicated in the widget Twig.
 *
 * `rows` is a ShoppingListRowsCollection::toArray() → `{items, products, bundles, pagination}` (items
 * carry `reference.{type,id,combinationData,options}` + comment/quantity/importance/priority/addedDate).
 * `lists` is the shopping-lists collection items (each list carries name/description/defaultOne/
 * keepPurchasedItems/priority). A widget with a paginator in its slot (shoppingList@2) gets the page asked in `page`,
 * as many rows as its pagination piece says ({@see pageOf()}); without one, EVERY row of the list ({@see allPages()}).
 * Shared by the full-page render
 * ({@see \Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider}) and the widget's AJAX
 * endpoint ({@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\WidgetContentHandler}
 * via {@see \Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry}).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\ShoppingList
 */
class ShoppingListService implements WidgetDataService {

    private const KEY = 'mffShoppingListRows';

    /** The largest page ShoppingListRowsParametersGroup::setPerPage accepts (the SDK validator stops at 100). */
    public const PER_PAGE_ALL = 100;

    /**
     * Pages asked at most for a list without paginator: 2,000 rows. It keeps the batch of the later pages from growing
     * with the list; a list that long is unusable in one block anyway.
     */
    public const MAX_PAGES = 20;

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $settings    = $widget !== null ? $widget->getModuleSettings() : [];
        $defaultSort = !empty($settings['defaultSort']) ? (string) $settings['defaultSort'] : null;
        return $this->fetch($defaultSort, self::pageOf(PaginationHelper::perPage($widget),
            RequestQuery::value(Parameters::PAGE, FilterInputFactory::getPaginableItemsParameter()[Parameters::PAGE])));
    }

    /**
     * @return array
     */
    /** @param array{0: int, 1: int}|null $pageOf [perPage, page] of a widget with paginator, or null without one */
    private function fetch(?string $defaultSort, ?array $pageOf): array {
        $listsCollection = Loader::service(Services::USER)->getShoppingLists(new ShoppingListsParametersGroup());
        $lists           = $listsCollection instanceof ElementCollection ? $listsCollection->toArray()['items'] : [];
        $listId          = $this->resolveListId($listsCollection);

        $rows = ['items' => [], 'products' => [], 'bundles' => [], 'pagination' => null];
        if ($listId > 0 && $pageOf !== null) {
            // shoppingList@2 pages with the pagination piece in its slot, as the orders do (#4627).
            $rows = $this->rowsPages($listId, $defaultSort, $pageOf[0], [$pageOf[1]])[$pageOf[1]] ?? $rows;
        } elseif ($listId > 0) {
            // Asked without a page size, the API answered its first page only, and the widget has no paginator: a
            // customer with more favourites than that page never saw the rest (#4627). The later pages go in one batch
            // (#8997, point 4 of request 447).
            $pages = fn(array $numbers): array => $this->rowsPages($listId, $defaultSort, self::PER_PAGE_ALL, $numbers);
            $rows = self::allPages($pages([1])[1], $pages) ?? $rows;
        }

        return ['lists' => $lists, 'rows' => $rows];
    }

    /**
     * [perPage, page] for a widget whose slot holds a pagination piece of $perPage rows: that size (up to the largest the
     * SDK takes) and the page asked ($page, the request's `page`; the first when absent or not a number). Null without
     * a paginator, and the list then comes whole.
     */
    public static function pageOf(?int $perPage, mixed $page): ?array {
        if ($perPage === null || $perPage <= 0) {
            return null;
        }
        return [min($perPage, self::PER_PAGE_ALL), is_numeric($page) ? max(1, (int) $page) : 1];
    }

    /**
     * Pages $numbers of the list's rows, all in one batch, each as `ShoppingListRowsCollection::toArray()` or null when
     * the API did not answer it.
     *
     * @param int[] $numbers
     * @return array<int, ?array> by page number
     */
    private function rowsPages(int $listId, ?string $defaultSort, int $perPage, array $numbers): array {
        $users = Loader::service(Services::USER);
        $requests = new BatchRequests();
        foreach ($numbers as $n) {
            $params = $this->buildParams($defaultSort);
            $params->setPerPage($perPage);
            $params->setPage($n);
            $users->addGetShoppingListRows($requests, self::KEY . $n, $listId, $params);
        }
        $answers = BatchService::getInstance()->send($requests);
        $pages = [];
        foreach ($numbers as $n) {
            $collection = $answers[self::KEY . $n] ?? null;
            $pages[$n] = $collection instanceof ShoppingListRowsCollection && $collection->getError() === null ? $collection->toArray() : null;
        }
        return $pages;
    }

    /**
     * Every row of the list from its first page ($first) and the API's later pages, asked all at once
     * ($pages([2, …, last]), up to {@see MAX_PAGES}): the rows in order and the products and bundles they name once
     * each. The pagination stays the API's first page: the widget without paginator does not read it. A page that does
     * not come or comes empty ends the rows: what came before it is kept. Null when the first page did not come.
     *
     * @param callable(int[]): array<int, ?array> $pages
     */
    public static function allPages(?array $first, callable $pages): ?array {
        if ($first === null) {
            return null;
        }
        $rows = $first + ['items' => [], 'products' => [], 'bundles' => []];
        $last = min((int) ($first['pagination']['totalPages'] ?? 1), self::MAX_PAGES);
        if ($last < 2) {
            return $rows;
        }
        $later = $pages(range(2, $last));
        for ($n = 2; $n <= $last; $n++) {
            $next = $later[$n] ?? null;
            if (!is_array($next) || ($next['items'] ?? []) === []) {
                break;
            }
            $rows['items'] = array_merge($rows['items'], $next['items']);
            $rows['products'] = self::union($rows['products'], $next['products'] ?? []);
            $rows['bundles'] = self::union($rows['bundles'], $next['bundles'] ?? []);
        }
        return $rows;
    }

    /** $known plus the elements of $more whose id it does not have yet. */
    private static function union(array $known, array $more): array {
        $ids = [];
        foreach ($known as $element) {
            $ids[(string) ($element['id'] ?? '')] = true;
        }
        foreach ($more as $element) {
            $id = (string) ($element['id'] ?? '');
            if (!isset($ids[$id])) {
                $ids[$id] = true;
                $known[] = $element;
            }
        }
        return $known;
    }

    /**
     * The list to fetch rows for: the requested `id` when it is one of the account's lists,
     * else the default list, else the first, else 0. Mirrors FWK
     * {@see \FWK\Core\Controllers\FiltrableShoppingListRowsTrait} (shoppingListRowsFilter.id).
     */
    private function resolveListId(?ElementCollection $lists): int {
        if (!$lists instanceof ElementCollection) {
            return 0;
        }
        $requested = (int) RequestQuery::value(Parameters::ID, FilterInputFactory::getIdParameter()[Parameters::ID]);
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
        $sort = RequestQuery::value(Parameters::SORT, FilterInputFactory::getSortableItemsParameters('ShoppingListRowsSort')[Parameters::SORT]);
        if ($sort !== null) {
            $params->setSort($sort);
        } elseif (!empty($defaultSort)) {
            $params->setSort(strtoupper($defaultSort));
        }
        $q = RequestQuery::value(Parameters::Q, RequestQuery::text());
        if ($q !== null) {
            $params->setQ($q);
        }
        return $params;
    }
}
