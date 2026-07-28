<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

/**
 * Bridge between BO `availablepages` values and FWK RouteTypes.
 * Header/footer overlays live in separate `useHeader`/`useFooter` properties.
 */
abstract class AvailablePagesValue {

    public const HOME = 'HOME_MODULE';

    public const PAGE = 'PAGE_MODULE';

    public const CATEGORY = 'CATEGORY_MODULE';

    public const PRODUCT = 'PRODUCT_MODULE';

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
        self::BLOG_HOME => 'BLOG_HOME',
        self::BLOG_CATEGORY => 'BLOG_CATEGORY',
        self::BLOG_POST => 'BLOG_POST',
        self::BLOG_TAGS => 'BLOG_TAG',
        self::BLOG_AUTHOR => 'BLOG_BLOGGER',
    ];
}
