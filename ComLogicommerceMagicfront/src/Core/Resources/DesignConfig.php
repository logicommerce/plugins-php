<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

/**
 * Config-driven design system for storefront page overrides.
 *
 * A "design" (e.g. design197) is ONE entry holding several sections:
 *   - productList : the product CARD config, SHARED by the category grid and the
 *                   product related-slider. A version (card template) + feature
 *                   detail keys (hoverImage, optionsLink, buyPanel, ribbons) +
 *                   pure show/hide flags.
 *   - categoryPage: how the category listing page is composed.
 *   - productPage : how the product detail page is composed.
 *
 * From one (page, design) we derive BOTH the CSS/JS asset list (served by the
 * design route handlers) AND the resolved snippet paths + flags the generic
 * page templates assemble.
 *
 * Asset paths are relative to assets/css/<page>/ and assets/js/<page>/.
 * Page component snippets live in mff-snippets/<page>/; the shared product card
 * snippets live in mff-snippets/category/ (reused by the product page).
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

    /** Shared product CARD: version => {css, snippet}. Snippet lives in mff-snippets/category/. */
    private const PRODUCT_LIST = [
        '01' => ['css' => 'lists/productList01.css'],
        '02' => ['css' => 'lists/productList02.css'],
        '04' => ['css' => 'lists/productList04.css'],
    ];

    /** Per-page component catalogue: component => version => {css:[], js:[], snippet, ...}. */
    private const COMPONENTS = [
        'category' => [
            'layout' => [
                'single' => ['css' => []],
                'column' => ['css' => ['src/columnLayout.css']],
            ],
            'breadcrumb' => [
                '01' => ['css' => ['modules/breadcrumb01.css'], 'snippet' => 'mff_breadcrumb-01'],
                '02' => ['css' => ['modules/breadcrumb02.css'], 'snippet' => 'mff_breadcrumb-02'],
            ],
            'categoryNav' => [
                'b1' => ['css' => ['modules/categoryNavB1.css'], 'snippet' => 'mff_categoryNav-b1'],
            ],
            'filter' => [
                '01' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter02.css'], 'js' => ['productsFilter02.js'], 'mode' => 'inline', 'snippet' => 'mff_productsFilter-01'],
                '03' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter03.css'], 'js' => ['productsFilter03.js'], 'mode' => 'inline', 'snippet' => 'mff_productsFilter-03'],
                '04' => ['css' => ['modules/productsFilter.css', 'modules/productsFilter04.css'], 'js' => ['productsFilter04.js'], 'mode' => 'column', 'snippetColumn' => 'mff_productsFilter-04Column', 'snippetContent' => 'mff_productsFilter-04Content'],
            ],
            // Grid layout (columns/gutters) only — the card comes from the shared productList.
            'grid' => [
                '01' => ['css' => ['modules/productsGrid01.css'], 'snippet' => 'mff_productsGrid01'],
                '02' => ['css' => ['modules/productsGrid02.css'], 'snippet' => 'mff_productsGrid-01'],
                '04' => ['css' => ['modules/productsGrid04.css'], 'snippet' => 'mff_productsGrid04'],
            ],
            'pagination' => [
                '01' => ['css' => ['modules/pagination01.css'], 'snippet' => 'mff_pagination-01'],
                '03' => ['css' => ['modules/pagination03.css'], 'snippet' => 'mff_pagination-03'],
            ],
            'categoriesGrid' => [
                '01' => ['css' => ['modules/categoriesGrid01.css', 'lists/categoryList01.css'], 'snippet' => 'mff_categoriesGrid-01'],
            ],
        ],
        'product' => [
            'breadcrumb' => [
                '01' => ['css' => ['modules/breadcrumb01.css'], 'snippet' => 'mff_breadcrumb-01'],
                '02' => ['css' => ['modules/breadcrumb02.css'], 'snippet' => 'mff_breadcrumb-02'],
            ],
            'pageTop' => [
                '01' => ['css' => ['modules/productPageTop01.css'], 'js' => ['productPageTop01.js'], 'snippet' => 'mff_productPageTop01'],
                '02' => ['css' => ['modules/productPageTop02.css'], 'js' => ['productPageTop02.js', 'stickyBuy01.js'], 'snippet' => 'mff_productPageTop02'],
                '03' => ['css' => ['modules/productPageTop03.css'], 'js' => ['productPageTop03.js'], 'snippet' => 'mff_productPageTop03'],
            ],
            'pageBottom' => [
                '01' => ['css' => ['modules/productPageBottom01.css'], 'js' => ['productPageBottom01.js'], 'snippet' => 'mff_productPageBottom01'],
                '02' => ['css' => ['modules/productPageBottom02.css'], 'js' => ['productPageBottom02.js'], 'snippet' => 'mff_productPageBottom02'],
            ],
            'relatedSlider' => [
                '01' => ['css' => ['modules/productsSlider01.css'], 'js' => ['productsSlider01.js'], 'snippet' => 'mff_productsSlider01'],
            ],
        ],
    ];

    /** Design presets: a shared productList card + per-page sections. productList needs only `version` plus deviations. */
    private const DESIGNS = [
        'design189' => [
            'productList' => ['version' => '04', 'hoverImage' => true],
            'categoryPage' => ['layout' => 'single', 'breadcrumb' => '02', 'breadcrumbPos' => 'content', 'filter' => '01', 'grid' => '01', 'pagination' => '03', 'categoriesGrid' => '01'],
            'productPage' => ['breadcrumb' => '02', 'pageTop' => '02', 'pageBottom' => '02', 'relatedSlider' => '01'],
        ],
        'design191' => [
            'productList' => ['version' => '02', 'buyPanel' => ['enabled' => true], 'orderBox' => ['quantity' => true]],
            'categoryPage' => ['layout' => 'single', 'breadcrumb' => '02', 'breadcrumbPos' => 'content', 'filter' => '03', 'grid' => '02', 'pagination' => '01', 'categoriesGrid' => '01'],
            'productPage' => ['breadcrumb' => '02', 'pageTop' => '01', 'pageBottom' => '01', 'relatedSlider' => '01'],
        ],
        'design197' => [
            'productList' => ['version' => '01'],
            'categoryPage' => ['layout' => 'column', 'breadcrumb' => '02', 'breadcrumbPos' => 'top', 'filter' => '04', 'grid' => '01', 'pagination' => '01', 'categoriesGrid' => null, 'categoryNav' => 'b1'],
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

    /**
     * Resolve a (page, design) into the structure the page template needs:
     * per-component snippet paths + the shared productList card config object.
     *
     * @param array $override optional live-preview overrides
     *        (productList / categoryPage / productPage), deep-merged onto the
     *        design before resolving; decoded from the `additionalData` request
     *        param by the editor preview.
     * @return array
     */
    public static function resolve(string $page, ?string $design, array $override = []): array {
        $design = self::resolveDesign($page, $design);
        $d = self::DESIGNS[$design] ?? [];
        $plCfg = $d['productList'] ?? [];
        if (!empty($override['productList']) && is_array($override['productList'])) {
            $plCfg = array_replace_recursive($plCfg, $override['productList']);
        }
        $pl = self::resolveProductList($plCfg);
        $secKey = self::PAGE_SECTION[$page];
        $section = $d[$secKey] ?? [];
        if (!empty($override[$secKey]) && is_array($override[$secKey])) {
            $section = array_replace_recursive($section, $override[$secKey]);
        }
        return $page === 'product'
            ? self::resolveProduct($design, $section, $pl)
            : self::resolveCategory($design, $section, $pl);
    }

    /**
     * Normalise the shared product-card config: card snippet + feature detail keys
     * + pure show/hide flags, all defaulted to current behaviour.
     *
     * @return array
     */
    private static function resolveProductList(array $pl): array {
        $version = $pl['version'] ?? '02';
        // v04 defaults its rich buy surface (panel + options + order box + comparison) on.
        $v04 = $version === '04';
        // Master switch: when on, the order box (add-to-cart) always renders; quantity/options are sub-toggles.
        $buyPanelEnabled = (bool) ($pl['buyPanel']['enabled'] ?? $v04);
        $ribbon = static fn(array $r, string $key): array => [
            'enabled' => (bool) ($r[$key]['enabled'] ?? ($r[$key] ?? true)),
        ];
        return [
            'version'     => $version,
            'snippet'     => 'mff-snippets/category/mff_productList.html.twig',
            'hoverImage'  => (bool) ($pl['hoverImage'] ?? false),
            'optionsLink' => (bool) ($pl['optionsLink'] ?? false),
            'buyOptions'  => $buyPanelEnabled && (bool) ($pl['buyOptions'] ?? $v04),
            'buyPanel'    => [
                'enabled' => $buyPanelEnabled,
                // Reveal derived from version: v04 overlays on hover, others inline always.
                'reveal'  => $v04 ? 'hover' : 'always',
            ],
            'orderBox'    => [
                'quantity' => $buyPanelEnabled && (bool) ($pl['orderBox']['quantity'] ?? true),
                'style'    => $pl['orderBox']['style'] ?? 'plus-minus',
            ],
            'ribbons'     => [
                'offer'     => $ribbon($pl['ribbons'] ?? [], 'offer'),
                'featured'  => $ribbon($pl['ribbons'] ?? [], 'featured'),
                'discounts' => $ribbon($pl['ribbons'] ?? [], 'discounts'),
            ],
            'flags'       => [
                'shoppingList' => (bool) ($pl['flags']['shoppingList'] ?? true),
                'comparison'   => (bool) ($pl['flags']['comparison'] ?? $v04),
                'stockAlert'   => (bool) ($pl['flags']['stockAlert'] ?? true),
            ],
        ];
    }

    /** @return array */
    private static function resolveCategory(string $design, array $cat, array $pl): array {
        $filter = self::COMPONENTS['category']['filter'][$cat['filter']];
        return [
            'design'         => $design,
            'layout'         => $cat['layout'],
            'breadcrumb'     => self::snippet('category', 'breadcrumb', $cat['breadcrumb']),
            'breadcrumbPos'  => $cat['breadcrumbPos'],
            'filterMode'     => $filter['mode'],
            'filterInline'   => ($filter['mode'] === 'inline') ? self::path('category', $filter['snippet']) : null,
            'filterColumn'   => ($filter['mode'] === 'column') ? self::path('category', $filter['snippetColumn']) : null,
            'filterContent'  => ($filter['mode'] === 'column') ? self::path('category', $filter['snippetContent']) : null,
            'grid'           => self::snippet('category', 'grid', $cat['grid']),
            'productList'    => $pl,
            'pagination'     => self::snippet('category', 'pagination', $cat['pagination']),
            'paginationPos'  => $cat['paginationPos'] ?? 'both',
            'categoriesGrid' => isset($cat['categoriesGrid']) ? self::snippet('category', 'categoriesGrid', $cat['categoriesGrid']) : null,
            'categoryNav'    => isset($cat['categoryNav']) ? self::snippet('category', 'categoryNav', $cat['categoryNav']) : null,
            // Auto-on for column layout (used by categoryNav + productsFilter04).
            'categoryColor'  => (($cat['layout'] ?? 'single') === 'column'),
        ];
    }

    /** @return array */
    private static function resolveProduct(string $design, array $prod, array $pl): array {
        return [
            'design'        => $design,
            'breadcrumb'    => self::snippet('product', 'breadcrumb', $prod['breadcrumb']),
            'breadcrumbPos' => $prod['breadcrumbPos'] ?? 'top',
            'pageTop'       => self::snippet('product', 'pageTop', $prod['pageTop']),
            'pageBottom'    => self::snippet('product', 'pageBottom', $prod['pageBottom']),
            'relatedSlider' => isset($prod['relatedSlider']) ? self::snippet('product', 'relatedSlider', $prod['relatedSlider']) : null,
            'productList'   => $pl,
            'categoryColor' => (bool) ($prod['categoryColor'] ?? false),
        ];
    }

    private static function snippet(string $page, string $component, ?string $version): ?string {
        if ($version === null || !isset(self::COMPONENTS[$page][$component][$version]['snippet'])) {
            return null;
        }
        return self::path($page, self::COMPONENTS[$page][$component][$version]['snippet']);
    }

    private static function path(string $page, string $snippet): string {
        return 'mff-snippets/' . $page . '/' . $snippet . '.html.twig';
    }
}
