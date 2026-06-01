<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services;

use SDK\Core\Dtos\Request;
use SDK\Core\Resources\Redis;
use SDK\Core\Resources\Url;
use SDK\Core\Services\CacheTrait;

/**
 * Drop-in alternative to SDK's CacheTrait that:
 *
 *   1. Skips caching on error responses. SDK's `RedisCache::getData` only
 *      recognises errors of the SDK Error DTO shape (`$data['error']` is an
 *      object with `getStatus()` / `getCode()`); flat error JSON returned by
 *      dcsapi (`{"error": "...", "detail": "..."}` with HTTP 4xx/5xx) slips
 *      past that check and gets cached for the full TTL, poisoning every
 *      subsequent request for 5 minutes. Here we check the body's `error`
 *      key (covers both shapes) and only write to Redis when it's absent.
 *
 *   2. Exposes a per-request opt-out via {@see disableCache()} for callers
 *      that must bypass cache contextually — e.g. handlers that only run in
 *      the canvas editor where in-iframe AJAX carries `Sec-Fetch-Dest: empty`
 *      so the storefront's iframe-detection alone wouldn't catch them.
 *
 * Uses SDK CacheTrait transitively to inherit `getCacheName()` and
 * `getCacheTtl()`. Its own `call()` is shadowed by the consumer's override.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services
 */
trait SuccessCacheTrait {

    use CacheTrait;

    private bool $bypassCache = false;

    /**
     * Marks this service instance to bypass cache for the rest of the
     * request. Returns $this so handlers can fluent-chain:
     *
     *   $service = MyService::getInstance()->disableCache();
     */
    public function disableCache(): self {
        $this->bypassCache = true;
        return $this;
    }

    /**
     * Reads/writes Redis directly so we can skip caching on error responses.
     * Falls back to a raw `parent::call()` when Redis is disabled.
     */
    protected function cacheSuccessOnly(Request $request, string $apiUrl): array {
        if (!Redis::isEnabled() || !defined('USE_CACHE_REDIS_OBJECT') || !USE_CACHE_REDIS_OBJECT) {
            return parent::call($request, $apiUrl);
        }
        $key    = $this->getCacheName($request->getPath() . Url::encodeParams($request->getUrlParams()));
        $cached = Redis::get($key);
        if ($cached !== null) {
            $data = json_decode($cached, true);
            if (!empty($data)) {
                return $data;
            }
        }
        $data = parent::call($request, $apiUrl);
        if (!isset($data['error']) && !empty($data)) {
            Redis::set($key, json_encode($data), $this->getCacheTtl());
        }
        return $data;
    }
}
