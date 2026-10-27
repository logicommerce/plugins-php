<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * The order detail section of the MagicFront account page (E22, #4508): `/accounts/used?mfPanel=orderDetail&mfOrderId=<id>`.
 *
 * - The store's address of an order (USER_ORDER / ACCOUNT_ORDER, the links of the order e-mails) goes to that section
 *   ONLY when the published account page has it (`content.accountSections`, #4504). Without it, it goes to the orders
 *   list, as it always did ({@see self::panelQuery()}).
 * - Two of LogiCommerce's own uses of that address must keep the store's page, never the redirect
 *   ({@see self::keepsStorePage()}): the «view order» popup, which loads it by AJAX into a modal (lc.plugins.js,
 *   `viewDocumentPopup`), and a guest's order, opened with `?token=` and no session. Before this, both were sent to
 *   the account page too: the popup got the whole page and the guest, the login.
 *
 * Pure, over plain values: the plugin suite has no FWK/SDK bootstrap in a bare checkout. Only {@see self::keepsStorePage()}
 * reads the request, through {@see MagicfrontUtils::isScriptRequest()} (the FWK's `REQUEST_HEADERS`, absent there).
 * NOTE (infra): `mfOrderId` must be on the Varnish query-param whitelist, like `mfPanel` ({@see PanelUrl}).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class OrderDetailUrl {

    /** URL query param carrying the order the detail section shows. */
    public const PARAM = 'mfOrderId';

    /** sectionKey of the order detail section. */
    public const SECTION = 'orderDetail';

    /** LogiCommerce's query param of a guest's order (FWK `Parameters::TOKEN`). */
    public const GUEST_TOKEN = 'token';

    /** sectionKey of the orders list: where an order address went before the detail section existed. */
    public const LIST_SECTION = 'orders';

    /** The order id asked for (a positive integer, digits only), or null. */
    public static function requestedId(mixed $raw): ?int {
        if (is_int($raw)) {
            return $raw > 0 ? $raw : null;
        }
        if (!is_string($raw) || preg_match('/^[0-9]{1,18}$/', $raw) !== 1) {
            return null;
        }
        $id = (int) $raw;
        return $id > 0 ? $id : null;
    }

    /**
     * The account page query an order address goes to: its detail when the published account page has that section,
     * the orders list otherwise. `$accountSections` null = a page published before the list existed: the list, as before.
     *
     * @param string[]|null $accountSections
     */
    public static function panelQuery(int $orderId, ?array $accountSections): string {
        if ($orderId > 0 && $accountSections !== null && in_array(self::SECTION, $accountSections, true)) {
            return 'mfPanel=' . self::SECTION . '&' . self::PARAM . '=' . $orderId;
        }
        return 'mfPanel=' . self::LIST_SECTION;
    }

    /**
     * Whether this request to the order address is one LogiCommerce serves itself: the «view order» popup, which a script
     * of the page asks for ({@see MagicfrontUtils::isScriptRequest()}, the same rule as F-884; the popup does not send
     * `X-Requested-With`), or a guest's order (`?token=`, which the caller reads through RequestQuery with the FWK's
     * token filter, as LogiCommerce reads it).
     */
    public static function keepsStorePage(?string $guestToken): bool {
        if (MagicfrontUtils::isScriptRequest()) {
            return true;
        }
        return $guestToken !== null && trim($guestToken) !== '';
    }
}
