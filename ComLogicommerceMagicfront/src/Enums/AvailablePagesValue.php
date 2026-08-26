<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * Bridge between BO `availablepages` values and FWK RouteTypes.
 * Header/footer overlays live in separate `useHeader`/`useFooter` properties.
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class AvailablePagesValue extends Enum {

    public const HOME = 'HOME_MODULE';

    public const PAGE = 'PAGE_MODULE';

    public const CATEGORY = 'CATEGORY_MODULE';

    public const PRODUCT = 'PRODUCT_MODULE';

    public const ACCOUNT = 'ACCOUNT_MODULE';

    public const BASKET = 'BASKET_MODULE';

    public const CHECKOUT = 'CHECKOUT_MODULE';

    public const BLOG_HOME = 'BLOG_HOME_MODULE';

    public const BLOG_CATEGORY = 'BLOG_CATEGORY_MODULE';

    public const BLOG_POST = 'BLOG_POST_MODULE';

    public const BLOG_TAGS = 'BLOG_TAGS_MODULE';

    public const BLOG_AUTHOR = 'BLOG_AUTHOR_MODULE';

    // BO uses the dcsapi blog naming (BLOG_TAGS / BLOG_AUTHOR); FWK route types are
    // BLOG_TAG / BLOG_BLOGGER — the map bridges the divergence.
    public const TO_ROUTE_TYPE = [
        self::HOME => 'HOME',
        self::PAGE => 'PAGE',
        self::CATEGORY => 'CATEGORY',
        self::PRODUCT => 'PRODUCT',
        self::ACCOUNT => 'ACCOUNT',
        self::BASKET => 'BASKET',
        self::CHECKOUT => 'CHECKOUT',
        self::BLOG_HOME => 'BLOG_HOME',
        self::BLOG_CATEGORY => 'BLOG_CATEGORY',
        self::BLOG_POST => 'BLOG_POST',
        self::BLOG_TAGS => 'BLOG_TAG',
        self::BLOG_AUTHOR => 'BLOG_BLOGGER',
    ];
}
