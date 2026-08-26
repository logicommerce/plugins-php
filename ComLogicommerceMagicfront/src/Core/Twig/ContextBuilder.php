<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Session\SessionGeneralSettings;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use SDK\Application;
use SDK\Dtos\Catalog\CategoryTree;
use SDK\Services\Parameters\Groups\AreaCategoriesTreeParametersGroup;

/**
 * Class B — runtime context. Single source of truth for the Twig globals and
 * environment values that {@see PluginTwigBootstrap} feeds into the storefront
 * fwk renderer AND, via `sync-plugin.sh`, into the docker template-renderer.
 * Two entry points, one per environment: `fromSession()` reads the fwk Session,
 * `fromArray()` takes the Java payload the isolated docker renderer receives.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class ContextBuilder {

    /** Widget macros only ship for bootstrap5 (twigCoreTemplates/macros/modes/bootstrap5/). */
    public const DEFAULT_CORE_MODE = 'bootstrap5';

    /** Nav category tree depth: top categories + 4 nested levels, so the categoryMenu's
     *  "extra levels" control (subcategories only … up to 3 deeper) always has data. */
    private const CATEGORY_NAV_DEPTH = 5;

    public function __construct(
        /** THE editor canvas: an iframe carrying a token. Widgets render mock, providers skip real
         *  data fetches, the layout ships both chrome variants and only the whitelisted canvas JS. */
        public readonly bool $canvasMode,
        /** Canvas OR the standalone preview tab. Unlocks editor-only behaviour both share:
         *  resolving un-published pages, honouring ?mffHeader/?mffFooter. NOT a mock switch. */
        public readonly bool $previewMode,
        public readonly string $coreMode,
        /** 2-letter ISO language ("es","en","ca") — the only locale-ish value any consumer reads. */
        public readonly ?string $language,
        /** Each entry: ['code'=>'es', 'name'=>'Castellano', 'url'=>'/es/...', 'isActive'=>bool]. Consumer: mff_getLanguages(). */
        public readonly array $languages = [],
        /** Each entry: ['code','symbol','name','codeNumber','usdValue','id','isActive']. Consumer: mff_getMoney(). */
        public readonly array $currencies = [],
        /** Top categories (parentId 0) each with one level of subcategories.
         *  Each entry: ['id','name','url','subcategories'=>[['id','name','url'],...]]. Consumer: mff_getCategories(). */
        public readonly array $categories = [],
        /** Stable blob pId of the page being rendered (mff_* for singleton routes, else the numeric page id).
         *  Exposed as the `mff_pagePId` Twig global so a widget can echo it (data-mff-page-pid) and its AJAX
         *  can address its own blob without relying on the request Referer. '' when unknown. */
        public readonly string $pagePId = '',
    ) {
        if ($this->coreMode === '') {
            throw new \InvalidArgumentException('ContextBuilder: coreMode is required.');
        }
        if ($this->language === '') {
            throw new \InvalidArgumentException('ContextBuilder: language must be non-empty when provided.');
        }
    }

    /**
     * docker template-renderer entry — the renderer has no fwk Session, so Java sends the values.
     * Reached via `docker/template-renderer/index.php`, which this file is rsynced into by
     * `sync-plugin.sh`; the call site lives in that repo, NOT in the plugin tree.
     */
    public static function fromArray(array $ctx): self {
        return new self(
            canvasMode: (bool) ($ctx[MagicfrontControllerData::CONTEXT_CANVAS_MODE] ?? false),
            previewMode: (bool) ($ctx[MagicfrontControllerData::CONTEXT_PREVIEW_MODE] ?? false),
            coreMode: self::requireString($ctx, MagicfrontControllerData::CONTEXT_CORE_MODE),
            language: self::optionalString($ctx, 'language'),
            languages: is_array($ctx['languages'] ?? null) ? $ctx['languages'] : [],
            currencies: is_array($ctx['currencies'] ?? null) ? $ctx['currencies'] : [],
            categories: is_array($ctx['categories'] ?? null) ? $ctx['categories'] : [],
            pagePId: is_string($ctx['pagePId'] ?? null) ? $ctx['pagePId'] : '',
        );
    }

    /**
     * fwk storefront entry. Session must already be initialised.
     *
     * `$withNav` builds the header category tree ({@see self::buildCategoriesFromArea}, an AREA
     * service call); a content-only partial render skips it (the nav is chrome, not rendered).
     */
    public static function fromSession(bool $withNav = true, string $pagePId = ''): self {
        $settings = Session::getInstance()->getGeneralSettings();
        return new self(
            canvasMode: RenderMode::isCanvasMode(),
            previewMode: RenderMode::isPreviewMode(),
            coreMode: self::DEFAULT_CORE_MODE,
            language: $settings->getLanguage(),
            languages: self::buildLanguagesFromSession($settings),
            currencies: self::buildCurrenciesFromSession($settings),
            categories: $withNav ? self::buildCategoriesFromArea() : [],
            pagePId: $pagePId,
        );
    }

    /**
     * Top-level nav categories, each nested {@see self::CATEGORY_NAV_DEPTH} levels deep — the data
     * the header categoryMenu renders (top cats = nav items, subcats = dropdown / megamenu tiers).
     *
     * Source: the storefront's CATEGORY-ROLE AREA tree via
     * {@see AreaService::getCategoriesAreaCategoriesTree()}. This is the same source the live
     * LogiCommerce themes use for their header menu, and the only one returning a real ROOT tree
     * with nested children: CategoryService::getCategoriesTree()/getCategories() return a flat
     * alphabetical page mixing all levels (no usable nesting), and getCategoriesByParentId(0)
     * throws (the SDK validator rejects parentId 0 — must be > 0).
     *
     * Never throws: a null tree (no category-role area configured) or any service failure yields []
     * so chrome still renders (the menu shows its empty-state placeholder).
     *
     * @return array
     */
    private static function buildCategoriesFromArea(): array {
        try {
            $params = new AreaCategoriesTreeParametersGroup();
            $params->setLevels(self::CATEGORY_NAV_DEPTH);
            $params->setOnlyActive(true);
            $tree = Loader::service(Services::AREA)->getCategoriesAreaCategoriesTree($params);
            if ($tree === null) {
                return [];
            }
            $out = [];
            foreach ($tree->getItems() as $category) {
                $entry = self::mapCategoryTree($category, self::CATEGORY_NAV_DEPTH);
                if ($entry !== null) {
                    $out[] = $entry;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            // Chrome must never break: swallow to [] (menu shows its empty-state). Logged so a
            // silently-empty category menu in production stays diagnosable.
            error_log('[MagicFront] header category menu unavailable: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Recursively map a CategoryTree to {id,name,url,subcategories:[...]} up to $depth levels.
     * url = getLink() (= getDestinationUrl() ?: getUrlSeo(); getDestinationUrl is only filled for
     * admin-set external redirects, so .link is what real storefront menus use). Returns null when
     * the category has no language payload (skipped: a nameless nav item is never rendered).
     *
     * @return array|NULL
     */
    private static function mapCategoryTree(CategoryTree $category, int $depth): ?array {
        $language = $category->getLanguage();
        if ($language === null) {
            return null;
        }
        $entry = [
            'id'            => $category->getId(),
            'name'          => $language->getName(),
            'url'           => $language->getLink(),
            'subcategories' => [],
        ];
        if ($depth > 1) {
            foreach ($category->getSubcategories() as $subcategory) {
                $child = self::mapCategoryTree($subcategory, $depth - 1);
                if ($child !== null) {
                    $entry['subcategories'][] = $child;
                }
            }
        }
        return $entry;
    }

    /** Currency payload widget templates can iterate; matches setCurrency JS `data-lc-data` shape. */
    private static function buildCurrenciesFromSession(SessionGeneralSettings $settings): array {
        $current = $settings->getCurrency();
        $out = [];
        foreach (Application::getInstance()->getCurrenciesSettings() as $currency) {
            $out[] = [
                'code'       => $currency->getCode(),
                'symbol'     => $currency->getSymbol(),
                'name'       => $currency->getName(),
                'codeNumber' => $currency->getCodeNumber(),
                'usdValue'   => $currency->getUSDValue(),
                'id'         => $currency->getId(),
                'isActive'   => $currency->getCode() === $current,
            ];
        }
        return $out;
    }

    /** Language payload for languageSwitcher: code + url + isActive per route language. */
    private static function buildLanguagesFromSession(SessionGeneralSettings $settings): array {
        $current = $settings->getLanguage();
        $out = [];
        foreach ($settings->getDefaultAvailableLanguages() as $lang) {
            $out[] = [
                'code'     => $lang->getCode(),
                'name'     => $lang->getName(),
                'url'      => $lang->getUrl(),
                'isActive' => $lang->getCode() === $current,
            ];
        }
        return $out;
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

    /** @return array */
    public function toGlobals(): array {
        return [
            MagicfrontControllerData::CONTEXT_CANVAS_MODE  => $this->canvasMode,
            MagicfrontControllerData::CONTEXT_PREVIEW_MODE => $this->previewMode,
            MagicfrontControllerData::CONTEXT_CORE_MODE    => $this->coreMode,
            'mff_pagePId'                                  => $this->pagePId,
        ];
    }
}
