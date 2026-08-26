<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * Stable pIds of the singleton MagicFront pages, set once at plugin install. Each backs a fixed
 * surface painted for every request of its kind — the content controllers (HOME/CATEGORY/PRODUCT/
 * ACCOUNT), the site chrome (CHROME = header/footer), and the overlay panels (PANELS = login/basket/mobile).
 *
 * Single source of truth for these literals: controller takeover, chrome injection and panel
 * injection all gate on whether the corresponding page exists (is published) so producción keeps
 * running the commerce's own code until the MagicFront page is published.
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class SpecialPagePId extends Enum {

    public const HOME = 'mff_HOME';

    public const CATEGORY = 'mff_CATEGORY';

    public const PRODUCT = 'mff_PRODUCT';

    public const CHROME = 'mff_CHROME';

    public const PANELS = 'mff_PANELS';

    public const ACCOUNT = 'mff_USER_AREA';

    public const CHECKOUT = 'mff_CHECKOUT';

    public const BLOG_HOME = 'mff_BLOG_HOME';

    public const BLOG_POST = 'mff_BLOG_POST';

    public const BLOG_CATEGORY = 'mff_BLOG_CATEGORY';

    // Blog tag/author pIds keep the dcsapi naming (TAGS/AUTHOR); the matching FWK route types
    // are BLOG_TAG / BLOG_BLOGGER (see AvailablePagesValue::TO_ROUTE_TYPE).
    public const BLOG_TAGS = 'mff_BLOG_TAGS';

    public const BLOG_AUTHOR = 'mff_BLOG_AUTHOR';

    /**
     * The singleton special-page pId a FWK RouteType maps to, or null for routes with no singleton
     * page (e.g. PAGE / pageModules, which carry their own per-page blob). Drives the takeover gate:
     * such a route only overrides the commerce controller when this page is published.
     */
    public static function forRouteType(string $routeType): ?string {
        return match ($routeType) {
            'HOME'          => self::HOME,
            'CATEGORY'      => self::CATEGORY,
            'PRODUCT'       => self::PRODUCT,
            'ACCOUNT'       => self::ACCOUNT,
            'CHECKOUT'      => self::CHECKOUT,
            'BLOG_HOME'     => self::BLOG_HOME,
            'BLOG_POST'     => self::BLOG_POST,
            'BLOG_CATEGORY' => self::BLOG_CATEGORY,
            'BLOG_TAG'      => self::BLOG_TAGS,
            'BLOG_BLOGGER'  => self::BLOG_AUTHOR,
            default         => null,
        };
    }
}
