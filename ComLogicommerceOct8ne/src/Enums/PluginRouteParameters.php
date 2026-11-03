<?php

namespace Plugins\ComLogicommerceOct8ne\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginRouteParameters enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginRouteParameters::PARAMETER_API_TOKEN
 * @see PluginRouteParameters::PARAMETER_BRANDS_LIST
 * @see PluginRouteParameters::PARAMETER_CALLBACK
 * @see PluginRouteParameters::PARAMETER_COMBINATION_ID
 * @see PluginRouteParameters::PARAMETER_CURRENCY
 * @see PluginRouteParameters::PARAMETER_CUSTOMER_EMAIL
 * @see PluginRouteParameters::PARAMETER_DIR
 * @see PluginRouteParameters::PARAMETER_INCLUDES
 * @see PluginRouteParameters::PARAMETER_LOCALE
 * @see PluginRouteParameters::PARAMETER_ORDER_BY
 * @see PluginRouteParameters::PARAMETER_PAGE
 * @see PluginRouteParameters::PARAMETER_PAGE_SIZE
 * @see PluginRouteParameters::PARAMETER_PRODUCT_ID
 * @see PluginRouteParameters::PARAMETER_PRODUCT_IDS
 * @see PluginRouteParameters::PARAMETER_QUANTITY
 * @see PluginRouteParameters::PARAMETER_REFERENCE
 * @see PluginRouteParameters::PARAMETER_SEARCH
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOct8ne\Enums;
 */
class PluginRouteParameters extends Enum {

    public const PARAMETER_API_TOKEN = 'apiToken';

    public const PARAMETER_BRANDS_LIST = 'brandsList';

    public const PARAMETER_CALLBACK = 'callback';

    public const PARAMETER_CATEGORY_ID_LIST = 'categoryIdList';

    public const PARAMETER_FILTER_CUSTOM_TAGS = 'filterCustomTag';

    public const PARAMETER_COMBINATION_ID = 'combinationId';

    public const PARAMETER_CURRENCY = 'currency';

    public const PARAMETER_CUSTOMER_EMAIL = 'customerEmail';

    public const PARAMETER_DIR = 'dir';

    public const PARAMETER_INCLUDES = 'includes';

    public const PARAMETER_LOCALE = 'locale';

    public const PARAMETER_ORDER_BY = 'orderBy';

    public const PARAMETER_PAGE = 'page';

    public const PARAMETER_PAGE_SIZE = 'pageSize';

    public const PARAMETER_PRODUCT_ID = 'productId';

    public const PARAMETER_PRODUCT_IDS = 'productIds';

    public const PARAMETER_QUANTITY = 'qty';

    public const PARAMETER_REFERENCE = 'reference';

    public const PARAMETER_SEARCH = 'search';
}