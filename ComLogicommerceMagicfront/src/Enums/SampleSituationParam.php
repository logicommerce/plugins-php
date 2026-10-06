<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * The editor's product simulator: the canvas URL (and the per-widget refresh) carries the situation the
 * SAMPLE product and category must be painted in, and the plugin forwards it to `GET /samples/{kind}` as
 * `stock` / `offer` / `reviews` / `listing`. The backend owns the meaning and the defaults (an unknown or missing
 * value is the sample as it ships); this side only copies what it got. A real product is never affected.
 * `wishlist` is not sample data but the simulated SHOPPER: it stays here, read by `mff_product_wishlist()`.
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class SampleSituationParam extends Enum {

    /** `in` | `low` | `reserve` | `out` */
    public const STOCK = 'mff_sample_stock';

    /** `on` | `off` */
    public const OFFER = 'mff_sample_offer';

    /** `some` | `none` — the sample product with or without reviews. */
    public const REVIEWS = 'mff_sample_reviews';

    /** `full` | `empty` — the sample category's listing with or without products. */
    public const LISTING = 'mff_sample_listing';

    /** `in` — the simulated shopper is logged in and has the sample product in their favourites. Canvas only. */
    public const WISHLIST = 'mff_sample_wishlist';

    /** Request param → backend query param of `GET /samples/{kind}`. */
    public const BACKEND = [self::STOCK => 'stock', self::OFFER => 'offer', self::REVIEWS => 'reviews', self::LISTING => 'listing'];

    /**
     * The backend query params for what the request carries, read through `$read(paramName)`; only the
     * non-empty ones, so a canvas without the simulator asks for exactly the URL it always asked for.
     *
     * @param callable $read
     * @return array
     */
    public static function fromRequest(callable $read): array {
        $out = [];
        foreach (self::BACKEND as $param => $query) {
            $value = $read($param);
            if (is_string($value) && trim($value) !== '') {
                $out[$query] = trim($value);
            }
        }
        return $out;
    }
}
