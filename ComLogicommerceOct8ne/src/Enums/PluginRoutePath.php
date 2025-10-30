<?php

namespace Plugins\ComLogicommerceOct8ne\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginRoutePath enumeration class.
 * This class declares property names enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginRoutePath::ADDTOWISHLIST
 * @see PluginRoutePath::CUSTOMERDATA
 * @see PluginRoutePath::GETADAPTERINFO
 * @see PluginRoutePath::GETCART
 * @see PluginRoutePath::GETORDERS
 * @see PluginRoutePath::GETORDERDETAILS
 * @see PluginRoutePath::PRODUCTINFO
 * @see PluginRoutePath::PRODUCTRELATED
 * @see PluginRoutePath::PRODUCTSUMMARY
 * @see PluginRoutePath::SEARCH
 * 
 * @see Enum
 *
 * @package Plugins\ComLogicommerceOct8ne\Enums;
 */
class PluginRoutePath extends Enum {

    public const ADDTOWISHLIST = 'AddToWishlist';

    public const CUSTOMERDATA = 'CustomerData';

    public const GETADAPTERINFO = 'GetAdapterInfo';

    public const GETCART = 'GetCart';

    public const GETORDERS = 'GetOrders';

    public const GETORDERDETAILS = 'GetOrderDetails';

    public const PRODUCTINFO = 'ProductInfo';

    public const PRODUCTRELATED = 'ProductRelated';

    public const PRODUCTSUMMARY = 'ProductSummary';

    public const SEARCH = 'Search';

}