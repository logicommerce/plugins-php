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
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class MagicfrontToken {

    public const MF_TOKEN = 'mfToken';

    /**
     * The token this request authenticates with: of the cookie's and the URL's, the one that expires LATER.
     *
     * Cookie first was wrong whenever the editor opened a page with a fresh `?mfToken=` while the cookie still
     * held an expired one: the Twig initializer fetches the page's header/footer (`/chrome/{id}`) BEFORE the
     * controller stages the URL token, so that fetch went out with the expired cookie, got a 401 and the page
     * painted without header and footer, silently. Taking the later expiry makes the answer independent of that
     * order, and a stale URL (a tab reloaded with an old token) still loses to a fresh cookie.
     */
    public static function getToken(): ?string {
        $cookie = Cookie::get(self::MF_TOKEN);
        $cookie = is_string($cookie) && $cookie !== '' ? $cookie : null;
        // The cookie is only persisted inside the canvas iframe, so a standalone preview request (top document)
        // authenticates itself from its own query token.
        $param = $_GET[self::MF_TOKEN] ?? null;
        $param = is_string($param) && $param !== '' ? $param : null;
        if ($cookie === null || $param === null || $cookie === $param) {
            return $param ?? $cookie;
        }
        return self::expiresAt($param) >= self::expiresAt($cookie) ? $param : $cookie;
    }

    /**
     * The `exp` claim of a JWT, read without verifying it (the API verifies; this only chooses which of two
     * tokens to send). An unreadable token counts as already expired.
     */
    private static function expiresAt(string $jwt): int {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return 0;
        }
        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if ($payload === false) {
            return 0;
        }
        $claims = json_decode($payload, true);
        return is_array($claims) && isset($claims['exp']) && is_numeric($claims['exp']) ? (int) $claims['exp'] : 0;
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
