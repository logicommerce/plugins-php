<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use Plugins\ComLogicommerceMagicfront\Core\Resources\DesignConfig;

/**
 * Shared by any designable storefront page controller (Category, Product, ...).
 *
 * One call from setData() wires up the per-design asset pipeline and exposes
 * these Twig variables for the page override template:
 *   - mffDesign        the resolved design key (e.g. "design189")
 *   - mffDesignConfig  the resolved config (layout + per-component snippet paths)
 *   - mffDesignCssUrl  plugin-route URL serving only this (page, design) CSS
 *   - mffDesignJsUrl   plugin-route URL serving only this (page, design) JS
 *
 * The override template reads mffDesignConfig to assemble the page, and links
 * mffDesignCssUrl / mffDesignJsUrl in the head / jsAssets blocks.
 */
trait RendersDesignAssetsTrait {

    private const PLUGIN_ROUTE_BASE = '/lc_ecom_internal/resources/plugin_route/com.logicommerce.magicfront';

    /**
     * @param string $pageType    page key (e.g. "category")
     * @param string|null $design requested design; invalid/null falls back to the default
     */
    protected function registerDesignAssets(string $pageType, ?string $design): void {
        // Live-preview override (editor preview / testing). Carried on the existing
        // Varnish-whitelisted `additionalData` param (we cannot add new whitelisted
        // params): base64(JSON({ design?, productList?, categoryPage?, productPage? })).
        // `design` selects the design; the section/productList maps deep-merge live edits.
        $override = [];
        $payloadParam = filter_input(INPUT_GET, \FWK\Enums\Parameters::ADDITIONAL_DATA);
        if (is_string($payloadParam) && $payloadParam !== '') {
            $payload = json_decode((string) base64_decode($payloadParam, true), true);
            if (is_array($payload)) {
                if (!empty($payload['design']) && is_string($payload['design'])) {
                    $design = $payload['design'];
                }
                foreach (['productList', 'categoryPage', 'productPage'] as $k) {
                    if (!empty($payload[$k]) && is_array($payload[$k])) {
                        $override[$k] = $payload[$k];
                    }
                }
            }
        }
        $design = DesignConfig::resolveDesign($pageType, $design);

        // `template` carries the design key (Varnish forwards only whitelisted params).
        // The CSS/JS handlers also need the override so the served bundle matches the
        // previewed component versions — forward the raw additionalData onto them.
        $query = '&page=' . $pageType . '&template=' . $design;
        if (is_string($payloadParam) && $payloadParam !== '') {
            $query .= '&' . \FWK\Enums\Parameters::ADDITIONAL_DATA . '=' . rawurlencode($payloadParam);
        }

        $this->setDataValue('mffDesign', $design);
        $this->setDataValue('mffDesignConfig', DesignConfig::resolve($pageType, $design, $override));
        $this->setDataValue('mffDesignCssUrl', self::PLUGIN_ROUTE_BASE . '?type=customizeDesignCSS' . $query);
        $this->setDataValue('mffDesignJsUrl', self::PLUGIN_ROUTE_BASE . '?type=customizeDesignJS' . $query);
    }
}
