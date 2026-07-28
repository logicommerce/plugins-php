<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Session\SessionGeneralSettings;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use SDK\Application;
use SDK\Dtos\Catalog\CategoryTree;
use SDK\Services\Parameters\Groups\AreaCategoriesTreeParametersGroup;

/**
 * Class B — runtime context. Single source of truth for the Twig globals and
 * locale-bound values that {@see PluginTwigBootstrap} feeds into both the
 * storefront fwk renderer and the docker template-renderer.
 */
final class ContextBuilder {

    /** Widget macros only ship for bootstrap5 (twigCoreTemplates/macros/modes/bootstrap5/). */
    public const DEFAULT_CORE_MODE = 'bootstrap5';

    /** Nav category tree depth: top categories + 4 nested levels, so the categoryMenu's
     *  "extra levels" control (subcategories only … up to 3 deeper) always has data. */
    private const CATEGORY_NAV_DEPTH = 5;

    public function __construct(
        public readonly bool $previewMode,
        public readonly string $coreMode,
        public readonly ?string $locale,
        /** 2-letter ISO language ("es","en","ca") — distinct from $locale (ICU "es_ES"). */
        public readonly ?string $language,
        public readonly ?string $currencyCode,
        public readonly ?string $currencySymbolOverride,
        /** Each entry: ['code'=>'es', 'url'=>'/es/...', 'isActive'=>bool]. Consumer: mff_getLanguages(). */
        public readonly array $languages = [],
        /** Each entry: ['code','symbol','name','codeNumber','usdValue','id','isActive']. Consumer: mff_getMoney(). */
        public readonly array $currencies = [],
        /** Top categories (parentId 0) each with one level of subcategories.
         *  Each entry: ['id','name','url','subcategories'=>[['id','name','url'],...]]. Consumer: mff_getCategories(). */
        public readonly array $categories = [],
    ) {
        if ($this->coreMode === '') {
            throw new \InvalidArgumentException('ContextBuilder: coreMode is required.');
        }
        if ($this->locale === '') {
            throw new \InvalidArgumentException('ContextBuilder: locale must be non-empty when provided.');
        }
        if ($this->language === '') {
            throw new \InvalidArgumentException('ContextBuilder: language must be non-empty when provided.');
        }
        if ($this->currencyCode === '') {
            throw new \InvalidArgumentException('ContextBuilder: currencyCode must be non-empty when provided.');
        }
    }

    /** docker template-renderer entry. Only `coreMode` is required. */
    public static function fromArray(array $ctx): self {
        return new self(
            previewMode: (bool) ($ctx[MagicfrontControllerData::CONTEXT_PREVIEW_MODE] ?? false),
            coreMode: self::requireString($ctx, MagicfrontControllerData::CONTEXT_CORE_MODE),
            locale: self::optionalString($ctx, 'locale'),
            language: self::optionalString($ctx, 'language'),
            currencyCode: self::optionalString($ctx, 'currencyCode'),
            currencySymbolOverride: self::optionalString($ctx, 'currencySymbolOverride'),
            languages: is_array($ctx['languages'] ?? null) ? $ctx['languages'] : [],
            currencies: is_array($ctx['currencies'] ?? null) ? $ctx['currencies'] : [],
            categories: is_array($ctx['categories'] ?? null) ? $ctx['categories'] : [],
        );
    }

    /** fwk storefront entry. Session must already be initialised. */
    public static function fromSession(): self {
        $settings = Session::getInstance()->getGeneralSettings();
        return new self(
            // Editor/preview signal (canvas iframe or mfToken URL param) — same test as
            // MagicfrontTrait::editorMode / TwigInitializer::isEditorRequest. Exposed to widgets as
            // the `previewMode` Twig global so they can render mock data only inside the editor.
            previewMode: MagicfrontUtils::isCanvasMode() || !empty($_GET[MagicfrontToken::MF_TOKEN]),
            coreMode: self::DEFAULT_CORE_MODE,
            locale: $settings->getLocale(),
            language: $settings->getLanguage(),
            currencyCode: $settings->getCurrency(),
            currencySymbolOverride: self::lookupAppCurrencySymbol($settings->getCurrency()),
            languages: self::buildLanguagesFromSession($settings),
            currencies: self::buildCurrenciesFromSession($settings),
            categories: self::buildCategoriesFromArea(),
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
     * @return array<int, array{id:int,name:string,url:string,subcategories:array<int,mixed>}>
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
     * @return array{id:int,name:string,url:string,subcategories:array<int,mixed>}|null
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
                'url'      => $lang->getUrl(),
                'isActive' => $lang->getCode() === $current,
            ];
        }
        return $out;
    }

    /** Merchant-configured symbol override for $code, or null if not set. */
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
            MagicfrontControllerData::CONTEXT_PREVIEW_MODE => $this->previewMode,
            MagicfrontControllerData::CONTEXT_CORE_MODE    => $this->coreMode,
        ];
    }
}
