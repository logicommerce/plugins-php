<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use SDK\Core\Resources\Cookie;

/**
 * Magicfront's plugin-scoped token storage.
 *
 * Backed by FWK's Cookie queue: Cookie::set stages the value and
 * Response::beforeOutput() flushes it via Cookie::send(), which runs AFTER
 * Session::startWritableSession's header_remove("Set-Cookie") has already
 * scrubbed the framework's session bootstrap header. That ordering is what
 * lets the Set-Cookie actually reach the browser and makes the token
 * available on the very first page load's CSS/JS subresource requests —
 * session storage couldn't guarantee that because the session cookie is
 * bootstrapped asynchronously via GetSessionController, which always lost
 * the race against synchronous <link>/<script> requests on first visit.
 */
class MagicfrontToken {

    public const MF_TOKEN = 'mfToken';

    public static function getToken(): ?string {
        $value = Cookie::get(self::MF_TOKEN);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Persist the given token when non-empty (e.g. the URL carries one)
     * and return the active token, falling back to the value already
     * stored when nothing new arrived.
     */
    public static function setToken(?string $token): ?string {
        if (!empty($token)) {
            Cookie::set(self::MF_TOKEN, $token);
            return $token;
        }
        return self::getToken();
    }

    public static function clearToken(): void {
        Cookie::unset(self::MF_TOKEN);
    }
}
