<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use FWK\Core\Resources\Session;
use SDK\Application;

/**
 * Class B — runtime context. Single source of truth for the Twig globals and
 * locale-bound values that {@see PluginTwigBootstrap} feeds into both the
 * storefront fwk renderer and the docker template-renderer.
 *
 * Adding a new ambient field:
 *   1) add a readonly constructor property
 *   2) hydrate it in fromSession() (storefront) and fromArray() (docker)
 *   3) if templates need direct access, expose it in toGlobals()
 *
 * Field nullability rule: locale-bound fields are nullable (`?string`),
 * because not every render path knows them (e.g. docker /renderWidget for a
 * widget that doesn't display money). Empty strings are still rejected —
 * "I have a value, it's blank" is always a bug. The consumers
 * ({@see PriceFormatter}) re-validate at the actual point of use, so the
 * error surfaces only when a function that needs the field is invoked.
 *
 * `currencySymbolOverride` is purely optional — null means "fall back to the
 * ICU symbol", which matches fwk's `outputHtmlCurrency` behaviour when
 * Application has no symbol override configured for the active currency.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class ContextBuilder {

    /**
     * The only theme/core mode MagicFront widgets ship macros for
     * (twigCoreTemplates/macros/modes/bootstrap5/). Both renderer entry points
     * use this — storefront because widgets are bootstrap5-only regardless of
     * the host theme, docker because it has no theme to derive a mode from.
     */
    public const DEFAULT_CORE_MODE = 'bootstrap5';

    public function __construct(
        public readonly bool $previewMode,
        public readonly string $coreMode,
        public readonly ?string $locale,
        public readonly ?string $currencyCode,
        public readonly ?string $currencySymbolOverride,
    ) {
        if ($this->coreMode === '') {
            throw new \InvalidArgumentException('ContextBuilder: coreMode is required.');
        }
        if ($this->locale === '') {
            throw new \InvalidArgumentException('ContextBuilder: locale must be non-empty when provided.');
        }
        if ($this->currencyCode === '') {
            throw new \InvalidArgumentException('ContextBuilder: currencyCode must be non-empty when provided.');
        }
    }

    /**
     * docker template-renderer entry. `coreMode` is the only universally
     * required field. `locale` / `currencyCode` are optional here so widgets
     * that don't display money render even when Java forwards a minimal
     * payload; PriceFormatter throws at call-time if a money-display widget
     * runs without them.
     */
    public static function fromArray(array $ctx): self {
        return new self(
            previewMode:            (bool) ($ctx['previewMode'] ?? false),
            coreMode:               self::requireString($ctx, 'coreMode'),
            locale:                 self::optionalString($ctx, 'locale'),
            currencyCode:           self::optionalString($ctx, 'currencyCode'),
            currencySymbolOverride: self::optionalString($ctx, 'currencySymbolOverride'),
        );
    }

    /**
     * fwk storefront entry. Pulls locale + currency from Session and the
     * merchant-configured symbol override from Application currencies (the
     * same lookup fwk's `outputHtmlCurrency()` does). Production rendering
     * is never preview mode. Session must already be initialised — empty
     * locale or currency throws via the constructor.
     */
    public static function fromSession(): self {
        $settings = Session::getInstance()->getGeneralSettings();
        return new self(
            previewMode:            false,
            coreMode:               self::DEFAULT_CORE_MODE,
            locale:                 $settings->getLocale(),
            currencyCode:           $settings->getCurrency(),
            currencySymbolOverride: self::lookupAppCurrencySymbol($settings->getCurrency()),
        );
    }

    /**
     * Find the merchant-configured symbol for $code. Returns null if no
     * override is set — PriceFormatter then uses the ICU symbol. Matches
     * fwk's behaviour: override only when explicitly configured.
     */
    private static function lookupAppCurrencySymbol(string $code): ?string {
        foreach (Application::getInstance()->getCurrenciesSettings() as $currency) {
            if ($currency->getCode() === $code) {
                return $currency->getSymbol();
            }
        }
        return null;
    }

    private static function requireString(array $ctx, string $key): string {
        $value = $ctx[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("ContextBuilder::fromArray missing required '{$key}'.");
        }
        return $value;
    }

    private static function optionalString(array $ctx, string $key): ?string {
        $value = $ctx[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("ContextBuilder::fromArray '{$key}' must be a non-empty string when present.");
        }
        return $value;
    }

    /** @return array<string, mixed> */
    public function toGlobals(): array {
        return [
            'previewMode' => $this->previewMode,
            'coreMode'    => $this->coreMode,
        ];
    }
}
