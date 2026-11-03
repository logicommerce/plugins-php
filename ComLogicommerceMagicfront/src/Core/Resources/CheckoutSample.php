<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontPageType;

/**
 * MagicFront's sample checkout (`GET /samples/checkout`) painted on a BASKET or CHECKOUT page in the editor, in place of
 * the visitor's own session: the view models the checkout widgets read (`page.checkoutBasket`, `page.basketContent`,
 * `page.checkoutPayments`…) and the session basket (`shared.session.basket`), already in the shape the storefront
 * serializes them. The widgets read the same keys in the shop and in the canvas, so they keep ONE code path.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class CheckoutSample {

    /** The `page.*` keys the sample may set; anything else in the file is ignored. */
    private const PAGE_KEYS = [
        'checkout', 'checkoutBasket', 'checkoutWarnings', 'checkoutPlaceOrder', 'checkoutPayments',
        'checkoutShippings', 'checkoutLinkeds', 'checkoutSelectableGifts', 'saveForLaterRows', 'rewardPoints', 'customTags',
    ];

    /** The `shared.session.*` keys the sample replaces. */
    private const SESSION_KEYS = ['basket', 'lockedStocksAggregateData'];

    private function __construct() {
    }

    /** Whether a page of `$pageType` paints the sample checkout in this request (editor canvas or preview tab only). */
    public static function paints(?string $pageType): bool {
        return self::isCheckoutPage($pageType) && RenderMode::isPreviewMode();
    }

    /** Whether `$pageType` is one of the pages the sample checkout belongs to (BASKET, CHECKOUT). */
    public static function isCheckoutPage(?string $pageType): bool {
        return $pageType === MagicfrontPageType::BASKET || $pageType === MagicfrontPageType::CHECKOUT;
    }

    /**
     * The page setters for the sample's `page` branch, as {@see PageRelationResolver::attachWidgetData()} takes them.
     *
     * @return array<string, array>
     */
    public static function assignments(array $sample): array {
        $page = is_array($sample['page'] ?? null) ? $sample['page'] : [];
        $assignments = [];
        foreach (self::PAGE_KEYS as $key) {
            if (is_array($page[$key] ?? null)) {
                $assignments['set' . ucfirst($key)] = $page[$key];
            }
        }
        return $assignments;
    }

    /** `$shared` with its session basket replaced by the sample's. */
    public static function withSession(array $shared, array $sample): array {
        $session = $sample['shared']['session'] ?? null;
        if (!is_array($session)) {
            return $shared;
        }
        $mirror = is_array($shared['session'] ?? null) ? $shared['session'] : [];
        foreach (self::SESSION_KEYS as $key) {
            if (array_key_exists($key, $session)) {
                $mirror[$key] = $session[$key];
            }
        }
        $shared['session'] = $mirror;
        return $shared;
    }
}
