<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Closure;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Filter\Filter;

/**
 * The category's own facets, for a listing whose buyer filter matched nothing (`page.categoryFacets`).
 *
 * LogiCommerce trims a list's facets to its result: Color=Negro leaves only the colours, widths and subcategory of the
 * one product found, and a filter that finds nothing comes back with no facets at all. The native store then paints
 * only «Clear filters» (productsFilter.html.twig), and so does the filters widget by default.
 *
 * `page.products` stays exactly as LogiCommerce returned it. When the buyer's filter found nothing, the category's
 * unfiltered facets go apart as `page.categoryFacets`, and the filters widget decides with its own setting whether to
 * show them (category 1228 with Color=Negro mate, F-539, #4029; reviewer 13-10, #6588). They are asked only then, after
 * the batch: a list with results, or a page the buyer did not filter, costs no extra request.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class CategoryFacets {

    private function __construct() {
    }

    /**
     * The category's facets when the buyer filtered and the list came back empty; null otherwise, without calling
     * $categoryList.
     *
     * @param Closure(): ?ElementCollection $categoryList the category's unfiltered list (one product, with its facets)
     */
    public static function whenEmpty(?ElementCollection $products, bool $buyerFiltered, Closure $categoryList): ?Filter {
        if (!$buyerFiltered || $products === null || count($products) > 0) {
            return null;
        }
        $filter = $categoryList()?->getFilter();
        return $filter instanceof Filter ? $filter : null;
    }
}
