<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Session;
use FWK\Core\Resources\Session\SessionGeneralSettings;

/**
 * The editor re-renders a widget through the plugin route (`getWidget(s)`), which carries no language of its own: the
 * session there is in the store's default language, while the canvas asks for its content language with `mff_lang`.
 * The content came in `mff_lang`, but every price is written by FWK's `outputHtmlCurrency` with the SESSION locale,
 * so an English canvas got «24,90 €» back instead of «€24.90».
 *
 * This scope makes the session's general settings speak `mff_lang` (language and locale, same country and currency)
 * for the duration of the render, in memory only: `$_SESSION` is never written, so neither the visitor's session nor
 * the next request change, and {@see restore()} puts the original object back. Only behind a preview token, as
 * {@see MagicfrontUtils::contentLanguage()}.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class ContentLocaleScope {

    private function __construct(private readonly ?SessionGeneralSettings $original) {
    }

    /** Enters the scope for `$language`, or a no-op scope when there is nothing to change. */
    public static function enter(?string $language, bool $hasToken): self {
        $language = strtolower(trim((string) $language));
        if (!$hasToken || $language === '' || !class_exists(Session::class)) {
            return new self(null);
        }
        try {
            $session  = Session::getInstance();
            $settings = $session->getGeneralSettings();
            if (strtolower($settings->getLanguage()) === $language) {
                return new self(null);
            }
            self::swap($session, new SessionGeneralSettings(self::settingsFor($settings, $language)));
            return new self($settings);
        } catch (\Throwable $e) {
            error_log('[MagicFront] content locale ' . $language . ' not applied to the render: ' . get_class($e) . ': ' . $e->getMessage());
            return new self(null);
        }
    }

    /** Leaves the scope: the session's own settings again. Safe to call on a no-op scope and more than once. */
    public function restore(): void {
        if ($this->original !== null) {
            self::swap(Session::getInstance(), $this->original);
        }
    }

    /**
     * The data of a SessionGeneralSettings for `$language`: everything as in `$settings` except the language and the
     * locale, computed like FWK does for a route ({@see \FWK\Core\Resources\Utils::calculateLocale}).
     */
    public static function settingsFor(SessionGeneralSettings $settings, string $language): array {
        return [
            SessionGeneralSettings::LOCALE                      => self::localeFor($settings->getCountry(), $language),
            SessionGeneralSettings::CURRENCY                    => $settings->getCurrency(),
            SessionGeneralSettings::COUNTRY                     => $settings->getCountry(),
            SessionGeneralSettings::LANGUAGE                    => $language,
            SessionGeneralSettings::STORE_URL                   => $settings->getStoreURL(),
            SessionGeneralSettings::DEFAULT_ROUTE               => $settings->getDefaultRoute(),
            SessionGeneralSettings::DEFAULT_THEME               => $settings->getDefaultTheme(),
            SessionGeneralSettings::DEFAULT_AVAILABLE_LANGUAGES => $settings->getDefaultAvailableLanguages(),
        ];
    }

    /** `language_COUNTRY` when ICU knows it (en_ES, ca_ES…), else the bare language — FWK's own rule. */
    public static function localeFor(string $country, string $language): string {
        $locale = $language . '_' . $country;
        return in_array($locale, \ResourceBundle::getLocales(''), true) ? $locale : $language;
    }

    /** The session keeps its settings in a protected field with no setter: a closure bound to its class sets it. */
    private static function swap(Session $session, SessionGeneralSettings $settings): void {
        \Closure::bind(static function (Session $s, SessionGeneralSettings $g): void {
            $s->generalSettings = $g;
        }, null, Session::class)($session, $settings);
    }
}
