<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * Config-driven design system for storefront page overrides.
 *
 * A "design" (e.g. design197) is ONE entry holding several sections:
 *   - productList : the product CARD, SHARED by the category grid and the
 *                   product related-slider; its `version` picks the card CSS.
 *   - categoryPage: the component versions the category listing page loads.
 *   - productPage : the component versions the product detail page loads.
 *
 * From one (page, design) we derive the CSS/JS asset list served by the design
 * route handlers (CustomizeDesignStyleHandler, CustomizeDesignScriptHandler).
 *
 * Asset paths are relative to assets/css/<page>/ and assets/js/<page>/.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class DesignConfig {

    /** Pages this config serves. */
    private const PAGES = ['category', 'product'];

    /** design section that backs each page. */
    private const PAGE_SECTION = [
        'category' => 'categoryPage',
        'product'  => 'productPage',
    ];

    /** Per-page fallback design when the requested one is invalid for that page. */
    private const DEFAULT_DESIGN = [
        'category' => 'design189',
        'product'  => 'design197',
    ];

    /** CSS every design of the page loads, before component groups. */
    private const BASE_CSS = [
        'category' => [
            'src/category.css',
            'components/quantity.css',
            'components/buttons.css',
            'lists/productList.css',
        ],
        'product' => [
            'src/product.css',
            'components/quantity.css',
            'components/buttons.css',
            'lists/productList.css',
            // Modals + bundles are included on every product design.
            'modules/modalProductContact.css',
            'modules/modalProductComments.css',
            'modules/modalProductDiscounts.css',
            'modules/modalItemRecommend.css',
            'modules/modalProductStockAlert.css',
            'modules/productBundles.css',
        ],
    ];

    /** Cascade order of page-section component groups (productList handled separately). */
    private const CSS_ORDER = [
        'category' => ['layout', 'categoryNav', 'breadcrumb', 'filter', 'grid', 'categoriesGrid', 'pagination'],
        'product'  => ['breadcrumb', 'pageTop', 'pageBottom', 'relatedSlider'],
    ];

    /** Shared product CARD: version => {css}. */
    private const PRODUCT_LIST = [
        '01' => ['css' => 'lists/productList01.css'],
        '02' => ['css' => 'lists/productList02.css'],
        '04' => ['css' => 'lists/productList04.css'],
    ];

    /** Per-page component catalogue: component => version => {css:[], js:[]}. */
    private const COMPONENTS = [
        'category' => [
            'layout' => [
                'single' => ['css' => []],
                'column' => ['css' => ['src/columnLayout.css']],
            ],
            'breadcrumb' => [
                '01' => ['css' => ['modules/breadcrumb01.css']],
                '02' => ['css' => ['modules/breadcrumb02.css']],
            ],
            'categoryNav' => [
                'b1' => ['css' => ['modules/categoryNavB1.css']],
            ],
            'filter' => [
                '01' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter02.css'], 'js' => ['productsFilter02.js']],
                '03' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter03.css'], 'js' => ['productsFilter03.js']],
                '04' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter04.css'], 'js' => ['productsFilter04.js']],
            ],
            // Grid layout (columns/gutters) only — the card comes from the shared productList.
            'grid' => [
                '01' => ['css' => ['modules/productsGrid01.css']],
                '02' => ['css' => ['modules/productsGrid02.css']],
                '04' => ['css' => ['modules/productsGrid04.css']],
            ],
            'pagination' => [
                '01' => ['css' => ['modules/pagination01.css']],
                '03' => ['css' => ['modules/pagination03.css']],
            ],
            'categoriesGrid' => [
                '01' => ['css' => ['modules/categoriesGrid01.css', 'lists/categoryList01.css']],
            ],
        ],
        'product' => [
            'breadcrumb' => [
                '01' => ['css' => ['modules/breadcrumb01.css']],
                '02' => ['css' => ['modules/breadcrumb02.css']],
            ],
            'pageTop' => [
                '01' => ['css' => ['modules/productPageTop01.css'], 'js' => ['productPageTop01.js']],
                '02' => ['css' => ['modules/productPageTop02.css'], 'js' => ['productPageTop02.js', 'stickyBuy01.js']],
                '03' => ['css' => ['modules/productPageTop03.css'], 'js' => ['productPageTop03.js']],
            ],
            'pageBottom' => [
                '01' => ['css' => ['modules/productPageBottom01.css'], 'js' => ['productPageBottom01.js']],
                '02' => ['css' => ['modules/productPageBottom02.css'], 'js' => ['productPageBottom02.js']],
            ],
            'relatedSlider' => [
                '01' => ['css' => ['modules/productsSlider01.css'], 'js' => ['productsSlider01.js']],
            ],
        ],
    ];

    /** Design presets: a shared productList card + per-page sections. */
    private const DESIGNS = [
        'design189' => [
            'productList' => ['version' => '04'],
            'categoryPage' => ['layout' => 'single', 'breadcrumb' => '02', 'filter' => '01', 'grid' => '01', 'pagination' => '03', 'categoriesGrid' => '01'],
            'productPage' => ['breadcrumb' => '02', 'pageTop' => '02', 'pageBottom' => '02', 'relatedSlider' => '01'],
        ],
        'design191' => [
            'productList' => ['version' => '02'],
            'categoryPage' => ['layout' => 'single', 'breadcrumb' => '02', 'filter' => '03', 'grid' => '02', 'pagination' => '01', 'categoriesGrid' => '01'],
            'productPage' => ['breadcrumb' => '02', 'pageTop' => '01', 'pageBottom' => '01', 'relatedSlider' => '01'],
        ],
        'design197' => [
            'productList' => ['version' => '01'],
            'categoryPage' => ['layout' => 'column', 'breadcrumb' => '02', 'filter' => '04', 'grid' => '01', 'pagination' => '01', 'categoriesGrid' => null, 'categoryNav' => 'b1'],
            'productPage' => ['breadcrumb' => '02', 'pageTop' => '02', 'pageBottom' => '02', 'relatedSlider' => '01'],
        ],
    ];

    public static function isValidPage(string $page): bool {
        return in_array($page, self::PAGES, true);
    }

    public static function isValidDesign(string $page, ?string $design): bool {
        return $design !== null
            && isset(self::DESIGNS[$design][self::PAGE_SECTION[$page] ?? '']);
    }

    public static function resolveDesign(string $page, ?string $design): string {
        return self::isValidDesign($page, $design) ? $design : (self::DEFAULT_DESIGN[$page] ?? '');
    }

    /** @return string[] CSS files for the (page, design), in cascade order. */
    public static function cssFiles(string $page, ?string $design, array $override = []): array {
        if (!self::isValidPage($page)) {
            return [];
        }
        $design = self::resolveDesign($page, $design);
        $section = self::sectionWithOverride($page, $design, $override);
        $files = self::BASE_CSS[$page] ?? [];
        foreach (self::CSS_ORDER[$page] ?? [] as $component) {
            $version = $section[$component] ?? null;
            if ($version === null) {
                continue;
            }
            $files = array_merge($files, self::COMPONENTS[$page][$component][$version]['css'] ?? []);
        }
        // Shared product card CSS (category grid + product related slider both render cards).
        $plCfg = self::DESIGNS[$design]['productList'] ?? [];
        if (!empty($override['productList']) && is_array($override['productList'])) {
            $plCfg = array_replace_recursive($plCfg, $override['productList']);
        }
        $plVersion = $plCfg['version'] ?? null;
        if ($plVersion !== null && isset(self::PRODUCT_LIST[$plVersion])) {
            $files[] = self::PRODUCT_LIST[$plVersion]['css'];
        }
        return array_values(array_unique($files));
    }

    /** @return string[] JS files for the (page, design). */
    public static function jsFiles(string $page, ?string $design, array $override = []): array {
        if (!self::isValidPage($page)) {
            return [];
        }
        $design = self::resolveDesign($page, $design);
        $section = self::sectionWithOverride($page, $design, $override);
        $files = [];
        foreach (self::CSS_ORDER[$page] ?? [] as $component) {
            $version = $section[$component] ?? null;
            if ($version === null) {
                continue;
            }
            $files = array_merge($files, self::COMPONENTS[$page][$component][$version]['js'] ?? []);
        }
        return array_values(array_unique($files));
    }

    /**
     * Decode the live-preview `additionalData` payload (base64 JSON) into an
     * override map keeping only the section/card keys. Returns [] on empty/invalid.
     *
     * @return array
     */
    public static function overrideFromPayload(?string $base64): array {
        $out = [];
        if (is_string($base64) && $base64 !== '') {
            $payload = json_decode((string) base64_decode($base64, true), true);
            if (is_array($payload)) {
                foreach (['productList', 'categoryPage', 'productPage'] as $k) {
                    if (!empty($payload[$k]) && is_array($payload[$k])) {
                        $out[$k] = $payload[$k];
                    }
                }
            }
        }
        return $out;
    }

    /** Page-section config with the live-preview section override deep-merged in. */
    private static function sectionWithOverride(string $page, string $design, array $override): array {
        $secKey = self::PAGE_SECTION[$page];
        $section = self::DESIGNS[$design][$secKey] ?? [];
        if (!empty($override[$secKey]) && is_array($override[$secKey])) {
            $section = array_replace_recursive($section, $override[$secKey]);
        }
        return $section;
    }
}
