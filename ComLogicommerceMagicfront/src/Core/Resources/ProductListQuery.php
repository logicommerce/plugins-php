<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\FilterInput\FilterInput;
use FWK\Core\FilterInput\FilterInputFactory;
use FWK\Enums\Parameters;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;

/**
 * The buyer's query on a category's product list (page, sort, brands, subcategories, price and the custom-tag and
 * option filters), read through {@see RequestQuery} with the FWK's own product-list filters
 * ({@see FilterInputFactory::getProductsListParameters()}) and applied to the SDK list parameters. A value the
 * filters reject is left out and the list keeps the category's default for it: `?orderBy=xxx`, `?sort=<script>`,
 * `?brandsList[]=abc` or `?category[]=-5` paint the normal category instead of a 500 (#4285).
 *
 * Field-name contract (values arrive as PHP arrays through the widgets' `[]` names):
 *   page                                   → setPage, from page 2 on
 *   sort | orderBy                         → setSort (ProductSort keys, each optionally `.asc`/`.desc`, comma separated)
 *   brandsList[]                           → setBrandsList(csv)
 *   category[]                             → setCategoryIdList(csv)
 *   priceRange "min;max"                   → setFromPrice + setToPrice
 *   filterCustomTag_<id>[]                 → addFilterCustomTag(id, value)
 *   filterCustomTagInterval_<id> "min;max" → addFilterCustomTagInterval(id, min, max)
 *   filterCustomTagRangeInterval_<id>[]    → addFilterCustomTagRangeInterval(id, intervalId)
 *   filterOption_<url-encoded name>[]      → addFilterOption(name, value)
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class ProductListQuery {

    /** The category widgets' name for the subcategory filter (the FWK's is `categoryIdList`). */
    public const CATEGORY = 'category';

    /** The name the category sort widgets send the sort with (`?orderBy=price.asc`); the FWK's is `sort`. */
    public const ORDER_BY = 'orderBy';

    /**
     * Applies the request's list query to $params.
     *
     * @return bool whether the buyer narrowed the list with a filter (brand, subcategory, price, custom tag or option);
     *              page and sort do not count
     */
    public static function apply(ProductsParametersGroup $params): bool {
        $filters = FilterInputFactory::getProductsListParameters();
        $page = (int) RequestQuery::value(Parameters::PAGE, $filters[Parameters::PAGE]);
        if ($page > 1) {
            $params->setPage($page);
        }
        $sort = RequestQuery::value(Parameters::SORT, $filters[Parameters::SORT])
            ?? RequestQuery::value(self::ORDER_BY, $filters[Parameters::SORT]);
        if ($sort !== null) {
            $params->setSort($sort);
        }
        $filtered = false;
        $brands = RequestQuery::values(Parameters::BRANDS_LIST, RequestQuery::positiveInt());
        if ($brands !== []) {
            $params->setBrandsList(implode(',', $brands));
            $filtered = true;
        }
        $categories = RequestQuery::values(self::CATEGORY, RequestQuery::positiveInt());
        if ($categories !== []) {
            $params->setCategoryIdList(implode(',', $categories));
            $filtered = true;
        }
        $priceRange = self::range(RequestQuery::value(Parameters::PRICE_RANGE, $filters[Parameters::PRICE_RANGE]));
        if ($priceRange !== null) {
            $params->setFromPrice((float) $priceRange[0]);
            $params->setToPrice((float) $priceRange[1]);
            $filtered = true;
        }
        return self::applyDynamic($params, $filters) || $filtered;
    }

    /** The dynamic-name filters, `<paramName>_<identifier>` ({@see RequestQuery::dynamic()}). */
    private static function applyDynamic(ProductsParametersGroup $params, array $filters): bool {
        $filtered = false;
        $customTagId = RequestQuery::positiveInt();
        foreach (RequestQuery::dynamic(Parameters::FILTER_CUSTOMTAG_INTERVAL, $customTagId, $filters[Parameters::FILTER_CUSTOMTAG_INTERVAL]) as $id => $values) {
            $interval = self::range($values[0]);
            if ($interval !== null) {
                $params->addFilterCustomTagInterval((int) $id, $interval[0], $interval[1]);
                $filtered = true;
            }
        }
        foreach (RequestQuery::dynamic(Parameters::FILTER_CUSTOMTAG_RANGE_INTERVAL, $customTagId, $filters[Parameters::FILTER_CUSTOMTAG_RANGE_INTERVAL]) as $id => $intervalIds) {
            foreach ($intervalIds as $intervalId) {
                $params->addFilterCustomTagRangeInterval((int) $id, $intervalId);
            }
            $filtered = true;
        }
        foreach (RequestQuery::dynamic(Parameters::FILTER_CUSTOMTAG, $customTagId, $filters[Parameters::FILTER_CUSTOMTAG]) as $id => $tagValues) {
            foreach ($tagValues as $tagValue) {
                $params->addFilterCustomTag((int) $id, $tagValue);
            }
            $filtered = true;
        }
        foreach (RequestQuery::dynamic(Parameters::FILTER_OPTION, RequestQuery::exact(), $filters[Parameters::FILTER_OPTION]) as $name => $optionValues) {
            foreach ($optionValues as $optionValue) {
                $params->addFilterOption(rawurldecode((string) $name), $optionValue);
            }
            $filtered = true;
        }
        return $filtered;
    }

    /**
     * A `min;max` pair, lower bound first (the widgets send the slider's two ends in either order); null when $value
     * is absent or a single number, or when an end does not fit in a float: 309 digits or more pass the FWK's range
     * pattern but read as INF, and the SDK's validator rejects INF when the batch is built (the page answered 500).
     *
     * @return array{0: string, 1: string}|null
     */
    private static function range(?string $value): ?array {
        if ($value === null || !str_contains($value, FilterInput::REGEX_VALIDATE_RANGE_SEPARATOR)) {
            return null;
        }
        [$from, $to] = explode(FilterInput::REGEX_VALIDATE_RANGE_SEPARATOR, $value, 2);
        if (!is_finite((float) $from) || !is_finite((float) $to)) {
            return null;
        }
        return [(string) min($from, $to), (string) max($from, $to)];
    }
}
