<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use SDK\Application;
use SDK\Enums\PluginConnectorType;

/**
 * This class returns the PUBLIC key of the store's captcha plugin (Google reCAPTCHA v3 `siteKey`), the one the browser
 * needs to ask Google for a token. LogiCommerce validates that token on the server (FWK's CheckCaptcha, with the
 * plugin's secret `apiKey`), but the browser side was left to the store theme: it defines the global
 * `setCaptchaToken(callback)` that LC.Form and our forms call before sending. A MagicFront page whose theme does not
 * define it sent the field empty and LogiCommerce refused the sign-in. The layout (magicfront.html.twig) uses this key
 * to define it.
 *
 * Only the public key leaves this class: the plugin's other properties hold its secret.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class CaptchaSiteKey {

    /** The plugin property with the public key (ComLogicommerceGooglecaptcha's PluginPropertiesPropertyNames::SITEKEY). */
    public const SITE_KEY_PROPERTY = 'siteKey';

    private static ?string $siteKey = null;

    /**
     * Returns the public key of the store's captcha plugin, or '' when the store has none. Asks LogiCommerce once per
     * request, and only when a captcha plugin is there.
     *
     * @return string
     */
    public static function get(): string {
        if (self::$siteKey === null) {
            self::$siteKey = self::read();
        }
        return self::$siteKey;
    }

    /**
     * Returns the module of the first plugin that provides the captcha type, as FWK's
     * PluginService::getCaptchaPluginProperties() takes the first one, or '' when there is none.
     *
     * @param iterable $plugins the store's plugins (getModule(), getConnectors())
     * @return string
     */
    public static function getCaptchaModule(iterable $plugins): string {
        foreach ($plugins as $plugin) {
            foreach ($plugin->getConnectors() as $type) {
                if (strtoupper((string) $type) === PluginConnectorType::CAPTCHA) {
                    return $plugin->getModule();
                }
            }
        }
        return '';
    }

    /**
     * Returns the value of the public key among the plugin's properties (getName(), getValue()), or ''.
     *
     * @param iterable $properties
     * @return string
     */
    public static function getSiteKeyOf(iterable $properties): string {
        foreach ($properties as $property) {
            if ($property->getName() === self::SITE_KEY_PROPERTY) {
                return trim((string) $property->getValue());
            }
        }
        return '';
    }

    private static function read(): string {
        $module = self::getCaptchaModule(Application::getInstance()->getEcommercePlugins()?->getItems() ?? []);
        if ($module === '') {
            return '';
        }
        $pluginProperties = Loader::service(Services::PLUGIN)->getPluginPropertiesByModule($module);
        if ($pluginProperties === null || !method_exists($pluginProperties, 'getProperties')) {
            return '';
        }
        return self::getSiteKeyOf($pluginProperties->getProperties());
    }
}
