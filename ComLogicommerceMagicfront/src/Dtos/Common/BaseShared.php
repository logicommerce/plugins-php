<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Dtos\Common;

use Plugins\ComLogicommerceMagicfront\Enums\SharedPluginKeys;
use SDK\Application;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\Traits\ElementTrait;

/**
 * The part of `shared` every widget gets, whatever the providers: the store's licenses and what its plugins provide.
 * The commerce data the FWK Twig globals do not carry. Its properties are the keys of `shared` (toArray()).
 *
 * @see \Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry::collectShared()
 *
 * @package Plugins\ComLogicommerceMagicfront\Dtos\Common
 */
class BaseShared extends Element {
    use ElementTrait;

    protected array $licenses = [];

    protected array $plugins = [];

    /**
     * Returns the base `shared` of this request, read from what the FWK loads for every request (Application): it asks
     * the API nothing.
     *
     * @return BaseShared
     */
    public static function create(): self {
        $baseShared = new self();
        $baseShared->setLicenses(self::getApplicationLicenses());
        $baseShared->setPlugins(self::getApplicationPlugins());
        return $baseShared;
    }

    /**
     * Sets the active licenses, by pId, raw as LogiCommerce returns them (`shared.licenses`).
     *
     * @param array $licenses
     */
    public function setLicenses(array $licenses): void {
        $this->licenses = $licenses;
    }

    /**
     * Sets the plugin types of the store, by key of SharedPluginKeys (`shared.plugins`).
     *
     * @param array<string, SharedPlugin> $plugins
     */
    public function setPlugins(array $plugins): void {
        $this->plugins = $plugins;
    }

    /**
     * Returns the active licenses, by pId.
     *
     * @return array
     */
    public function getLicenses(): array {
        return $this->licenses;
    }

    /**
     * Returns the plugin types of the store, by key of SharedPluginKeys.
     *
     * @return array<string, SharedPlugin>
     */
    public function getPlugins(): array {
        return $this->plugins;
    }

    /**
     * Returns the active licenses of the store, by pId, raw and uninterpreted: widgets decide tiers themselves.
     */
    private static function getApplicationLicenses(): array {
        $licenses = [];
        foreach ((Application::getInstance()->getEcommerceLicenses()?->getLicenses() ?? []) as $license) {
            $pId = $license->getPId();
            if ($pId !== null && $pId !== '') {
                $licenses[$pId] = json_decode(json_encode($license), true);
            }
        }
        return $licenses;
    }

    /**
     * Returns each plugin type of SharedPluginKeys, enabled when a plugin of the store provides it. As FWK's
     * PluginService (getCaptchaPluginProperties()…): a plugin of that type is there.
     */
    private static function getApplicationPlugins(): array {
        $provided = [];
        foreach ((Application::getInstance()->getEcommercePlugins()?->getItems() ?? []) as $plugin) {
            foreach ($plugin->getConnectors() as $type) {
                $provided[strtoupper($type)] = true;
            }
        }
        $plugins = [];
        foreach (SharedPluginKeys::getValues() as $type => $key) {
            $plugin = new SharedPlugin();
            $plugin->setEnabled(isset($provided[$type]));
            $plugins[$key] = $plugin;
        }
        return $plugins;
    }
}
