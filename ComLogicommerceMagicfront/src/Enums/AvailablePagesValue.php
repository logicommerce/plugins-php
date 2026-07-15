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

    public const TO_ROUTE_TYPE = [
        self::HOME => 'HOME',
        self::PAGE => 'PAGE',
        self::CATEGORY => 'CATEGORY',
        self::PRODUCT => 'PRODUCT',
    ];
}
