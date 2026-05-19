<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Session;

/**
 * Magicfront's plugin-scoped session storage. Persists per-user state
 * across requests so the editor mode survives in-storefront navigation.
 *
 * Backed by FWK Session for now; switching to a Cookie-based store later
 * on is a one-class change — call sites stay on the stable typed API.
 */
class MagicfrontSession {

    public const MF_TOKEN = 'mfToken';

    public static function getToken(): ?string {
        $value = Session::getInstance()->getValue(self::MF_TOKEN);
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Persist the given token when non-empty (e.g. the URL carries one)
     * and return the active token, falling back to the value already in
     * session when nothing new arrived.
     */
    public static function setToken(?string $token): ?string {
        if (!empty($token)) {
            Session::getInstance()->addValue(self::MF_TOKEN, $token);
            return $token;
        }
        return self::getToken();
    }

    public static function clearToken(): void {
        Session::getInstance()->addValue(self::MF_TOKEN, null);
    }
}
