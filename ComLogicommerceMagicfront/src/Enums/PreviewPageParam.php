<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use SDK\Core\Enums\Enum;

/**
 * The page the editor's preview paints (#465). It used to travel as `page`, the same name the store uses for
 * pagination (the sales agents' listings read `$_GET['page']`): a listing's «page 2» was taken for a page id, and a
 * preview page id reached the pagination. The editor and the backend now send `mfPage`: new MagicFront parameters of
 * the store URL are `mf` + camelCase, like LogiCommerce's own and our public `mfToken` / `mfPanel` (#4352). The
 * interim `mff_page` never reached a published plugin, so it is not read.
 *
 * Transition: each store runs its own plugin version and the editor/backend may be newer or older than it. The new
 * name wins; the old `page` is still accepted as a page id, but only when it is not a number — page ids are UUIDs or
 * Studio refs (`store:…`, `draft:…`), pagination is numeric, so the two can no longer be confused. Remove the
 * fallback once every editor sends `mfPage` (cleanup task opened with #465).
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class PreviewPageParam extends Enum {

    public const NAME = 'mfPage';

    /** The old name, read only as a fallback (see the class comment). */
    public const LEGACY_NAME = 'page';

    /**
     * The preview page id from the new and the old parameter values, or null when neither names a page.
     *
     * @param mixed $new    value of `mfPage`
     * @param mixed $legacy value of `page`
     */
    public static function resolve(mixed $new, mixed $legacy): ?string {
        if (is_scalar($new) && trim((string) $new) !== '') {
            return trim((string) $new);
        }
        if (is_scalar($legacy)) {
            $value = trim((string) $legacy);
            if ($value !== '' && !ctype_digit($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     * The preview page id a plugin-route request names, by either parameter (see {@see resolve()}). Every reader of
     * the preview page goes through here, so a handler cannot read one name and miss the other (#4353).
     *
     * @param object $request anything with `getRequestParamValue(string $name, bool $required)`: the plugin-route
     *                        controller
     */
    public static function fromRequest(object $request): ?string {
        return self::resolve(
            $request->getRequestParamValue(self::NAME, false),
            $request->getRequestParamValue(self::LEGACY_NAME, false)
        );
    }
}
