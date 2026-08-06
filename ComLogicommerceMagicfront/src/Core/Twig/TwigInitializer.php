<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use FWK\Twig\PluginTwigInitializer;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeAssets;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeDocument;
use Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Services\Parameters\Groups\PageParametersGroup;
use Twig\Environment;

/**
 * fwk Twig hook. Returns the overlay layout and pre-loads chrome (header/footer) widget data
 * into Twig globals when the overlay is enabled in BO.
 *
 * Chrome source per kind:
 *   - editor request (canvas iframe / mfToken URL param) → dcsapi (page.chrome doc refs).
 *   - storefront → the page's OWN embedded chrome (its controllerItem blob's content.header /
 *     content.footer) when present, else the generic mff_CHROME page fetched via the LC FOB.
 * Absent chrome → empty ChromeAssets → the theme renders its own header/footer (parent()).
 */
final class TwigInitializer implements PluginTwigInitializer {

    private const PLUGIN_MODULE = 'com.logicommerce.magicfront';

    /** Twig global: true in canvas preview → the layout renders both chrome variants per region. */
    private const CANVAS_CHROME_GLOBAL = 'mffCanvasChrome';

    /** Twig globals: which chrome starts live per region in canvas preview ('1' = ours, '0' = store). */
    private const HEADER_MFF_GLOBAL = 'mffHeader';

    private const FOOTER_MFF_GLOBAL = 'mffFooter';

    /** Standalone-preview URL params carrying the toolbar toggle choice per region ('1' = our
     *  chrome, '0' = the store's own). Named with the `mf` prefix to sit alongside `mfToken` —
     *  only honoured when that token is present. */
    private const PREVIEW_HEADER_PARAM = 'mfHeader';

    private const PREVIEW_FOOTER_PARAM = 'mfFooter';

    /** Memoized generic mff_CHROME document (fetched at most once per request via genericChrome()). */
    private ?ChromeDocument $genericChromeCache = null;

    private bool $genericChromeLoaded = false;

    /** Memoized mff_PANELS document (fetched at most once per request via storefrontPanels()). */
    private ?ChromeDocument $panelsCache = null;

    private bool $panelsLoaded = false;

    public function apply(
        Environment $main,
        Environment $core,
        string $routeType,
        array $controllerData
    ): ?string {
        $ctx = ContextBuilder::fromSession();
        PluginTwigBootstrap::apply($main, $ctx);
        PluginTwigBootstrap::applyLazyFunctions($core, $ctx);

        $properties = self::pluginProperties();
        // Canvas preview renders BOTH chrome variants per region (our widgets + the store's own) so
        // the editor toolbar can swap them in place with no reload — see magicfront.html.twig. In
        // canvas we prepare our chrome for both regions regardless of the BO toggle; production
        // (real visitors) stays gated by the BO toggle alone and only ever renders one variant.
        $canvasChrome = MagicfrontUtils::isCanvasMode() && MagicfrontToken::getToken() !== null;
        $boHeader = $properties !== null && $properties->isHeaderOverlayEnabled();
        $boFooter = $properties !== null && $properties->isFooterOverlayEnabled();
        // Canvas swaps both variants live via the bridge; the standalone preview tab (token but not
        // an iframe) has no toolbar/bridge, so it honours the toggle choice carried as ?mffHeader/
        // ?mffFooter. Production (no token) ignores the params → BO toggle alone.
        // Production gate: our chrome overrides only when the mff_CHROME page is published; until then
        // producción keeps the commerce's own header/footer. The editor (canvasChrome / preview token)
        // is never gated so chrome can be authored before publish.
        $chromePublished = $canvasChrome || ($properties !== null && $properties->pageExists(SpecialPagePId::CHROME));
        $headerOn = $canvasChrome || ($chromePublished && (self::previewChromeOverride(self::PREVIEW_HEADER_PARAM) ?? $boHeader));
        $footerOn = $canvasChrome || ($chromePublished && (self::previewChromeOverride(self::PREVIEW_FOOTER_PARAM) ?? $boFooter));
        $main->addGlobal(self::CANVAS_CHROME_GLOBAL, $canvasChrome);
        // Which variant starts LIVE in canvas per region — '1' = our chrome, '0' = the store's own.
        // Our chrome when the BO toggle is on (an active plugin defaults MagicFront to our chrome).
        $main->addGlobal(self::HEADER_MFF_GLOBAL, $boHeader ? '1' : '0');
        $main->addGlobal(self::FOOTER_MFF_GLOBAL, $boFooter ? '1' : '0');

        $main->addGlobal(MagicfrontControllerData::OVERRIDE_HEADER, $headerOn);
        $main->addGlobal(MagicfrontControllerData::OVERRIDE_FOOTER, $footerOn);

        $kinds = [];
        if ($headerOn) {
            $kinds[] = ChromeKind::Header;
        }
        if ($footerOn) {
            $kinds[] = ChromeKind::Footer;
        }
        if ($kinds === []) {
            return 'layouts/magicfront.html.twig';
        }

        // 2-letter ISO ("es","en","ca") — dcsapi LOCALIZED filter does exact equality.
        $language = $ctx->language ?? '';

        // Editor: each page points at a header/footer chrome doc id via page.chrome.{kind}
        // (its own fork, or the shared default); exposed by the trait as PAGE_CHROME.
        $pageChrome = $controllerData[MagicfrontControllerData::PAGE_CHROME] ?? [];
        if (!is_array($pageChrome)) {
            $pageChrome = [];
        }

        $editor = self::isEditorRequest();
        $pageChromeBlob = $editor ? null : self::pageChromeBlob($controllerData);

        foreach ($kinds as $kind) {
            $assets = $editor
                ? $this->chromeFromApi($kind, $language, $pageChrome[$kind->value] ?? null)
                : $this->chromeFromBlob($kind, $this->resolveChromeBlob($kind, $pageChromeBlob));
            $this->emit($main, $kind, $assets);
        }

        // Login / basket / mobile-menu panels: the self-contained MFF panels (published mff_PANELS
        // blob) replace the commerce login/basket offcanvas and the theme's own mobile-menu nav so our
        // header's triggers open them; LC JS binds to their re-injected ids + data-lc hooks. Emitted
        // wherever our header can show — including the editor canvas (fetched via the LC FOB, works
        // with the preview token). Without this the account/cart/hamburger triggers do nothing.
        $panelsPublished = $editor || ($properties !== null && $properties->pageExists(SpecialPagePId::PANELS));
        if ($headerOn && $panelsPublished) {
            $panels = $this->storefrontPanels();
            foreach ([ChromeKind::AccountPanel, ChromeKind::BasketPanel, ChromeKind::MobileMenuPanel] as $kind) {
                $doc = ($panels !== null && $panels->hasKind($kind)) ? $panels : null;
                $this->emit($main, $kind, self::reinjectLcIds($this->chromeFromBlob($kind, $doc)));
            }
        }

        // Overlay layout — blocks fall back to merchant's parent() when a region is empty.
        return 'layouts/magicfront.html.twig';
    }

    /** Maps a built region onto its Twig globals (keys owned by the ChromeKind). */
    private function emit(Environment $main, ChromeKind $kind, ChromeAssets $assets): void {
        $main->addGlobal($kind->pagesGlobalKey(), $assets->pages);
        $main->addGlobal($kind->templatesGlobalKey(), $assets->templateList);
        $main->addGlobal($kind->cssGlobalKey(), $assets->css);
        $main->addGlobal($kind->jsGlobalKey(), $assets->js);
    }

    /**
     * Editor source: fetch the chrome doc from dcsapi by id (the page's own fork or the shared
     * default), or — when the page carries no ref — the commerce default of the kind
     * (`GET /chrome/{kind}?default=true`, backend lazy-seeds it).
     */
    private function chromeFromApi(ChromeKind $kind, string $language, ?string $chromeId): ChromeAssets {
        $service = WidgetsService::getInstance();
        $widgets = ($chromeId !== null && $chromeId !== '')
            ? $service->getChromeDoc($chromeId, $language)
            : $service->getChromeDoc($kind->value, $language, true);
        if ($widgets === []) {
            return ChromeAssets::empty();
        }
        $types = WidgetTypeCollector::fromWidgets(WidgetTypeCollector::flatten($widgets));
        $templates = $types !== [] ? $service->getWidgetTemplatesForTypes($types) : [];
        // getChromeDoc already returns hydrated WidgetInstance objects — wrap them in a base
        // ElementCollection (direct assign, no re-hydration) so fromWidgets takes one shape.
        return ChromeAssets::fromWidgets(new ElementCollection(['items' => $widgets]), $templates);
    }

    /**
     * Storefront source: build the region from the resolved chrome document (page-own or generic
     * mff_CHROME). A null document → empty region.
     */
    private function chromeFromBlob(ChromeKind $kind, ?ChromeDocument $chrome): ChromeAssets {
        if ($chrome === null) {
            return ChromeAssets::empty();
        }
        return ChromeAssets::fromWidgets($chrome->widgetsFor($kind), $chrome->templatesById());
    }

    /**
     * Storefront publish step for panels: re-inject the real element ids the theme trigger and LC
     * JS key off (the panel markup ships only data-lc-id so harvested id-scoped CSS never out-
     * specifies the editable instance CSS). e.g. data-lc-id="smallLoginOffcanvas" also gets
     * id="smallLoginOffcanvas" so #smallLoginOffcanvas opens the offcanvas.
     */
    private static function reinjectLcIds(ChromeAssets $assets): ChromeAssets {
        $templateList = [];
        foreach ($assets->templateList as $type => $html) {
            $templateList[$type] = preg_replace('/data-lc-id="([^"]+)"/', 'id="$1" data-lc-id="$1"', $html);
        }
        return new ChromeAssets($assets->pages, $templateList, $assets->css, $assets->js);
    }

    /**
     * Picks the storefront chrome document for one kind: the page's own embedded chrome when it
     * carries that kind, else the generic mff_CHROME (fetched lazily, once).
     */
    private function resolveChromeBlob(ChromeKind $kind, ?ChromeDocument $pageChrome): ?ChromeDocument {
        if ($pageChrome !== null && $pageChrome->hasKind($kind)) {
            return $pageChrome;
        }
        return $this->genericChrome();
    }

    /**
     * The current page's OWN chrome, read from the controllerItem when it is a MagicFront blob
     * page (home / magic page). Its content.header / content.footer, if present, override the
     * generic mff_CHROME per kind. Null for non-blob pages (e.g. commerce category / product,
     * whose controllerItem is a Category / Product DTO, not a Page).
     *
     * @param array<string, mixed> $controllerData
     */
    private static function pageChromeBlob(array $controllerData): ?ChromeDocument {
        $page = $controllerData[Controller::CONTROLLER_ITEM] ?? null;
        if (!$page instanceof Page) {
            return null;
        }
        return ChromeDocument::fromJson($page->getLanguage()?->getPageContent());
    }

    /**
     * The generic mff_CHROME document, fetched lazily and once — only reached when a shown kind is
     * not supplied by the page's own blob. Synchronous by pId via the LC FOB, so customers (who are
     * unauthenticated for dcsapi) never hit that API.
     */
    private function genericChrome(): ?ChromeDocument {
        if (!$this->genericChromeLoaded) {
            $this->genericChromeLoaded = true;
            $this->genericChromeCache = $this->loadStorefrontChrome();
        }
        return $this->genericChromeCache;
    }

    private function loadStorefrontChrome(): ?ChromeDocument {
        return $this->loadBlobPage(SpecialPagePId::CHROME);
    }

    /** The mff_PANELS document, fetched lazily and once via the LC FOB (same as the chrome page). */
    private function storefrontPanels(): ?ChromeDocument {
        if (!$this->panelsLoaded) {
            $this->panelsLoaded = true;
            $this->panelsCache = $this->loadBlobPage(SpecialPagePId::PANELS);
        }
        return $this->panelsCache;
    }

    private function loadBlobPage(string $pId): ?ChromeDocument {
        $params = new PageParametersGroup();
        $params->setPId($pId);
        $collection = Loader::service(Services::PAGE)->getPages($params);
        if (!$collection instanceof ElementCollection) {
            return null;
        }
        $page = $collection->getItems()[0] ?? null;
        if (!$page instanceof Page) {
            return null;
        }
        return ChromeDocument::fromJson($page->getLanguage()?->getPageContent());
    }

    /**
     * Editor request signal — canvas iframe or a non-empty mfToken URL param. Same test as
     * PluginProperties::isPreviewRequest and MagicfrontTrait's editorMode.
     */
    private static function isEditorRequest(): bool {
        return MagicfrontUtils::isCanvasMode() || !empty($_GET[MagicfrontToken::MF_TOKEN]);
    }

    /**
     * Standalone-preview per-region override from the toolbar's "open preview" URL. Returns true
     * when `$param` is 'mff', false when 'store', null when absent. Gated on the preview token so a
     * real visitor's request (no token) can never toggle chrome via the query string.
     */
    private static function previewChromeOverride(string $param): ?bool {
        if (MagicfrontToken::getToken() === null || !isset($_GET[$param])) {
            return null;
        }
        return $_GET[$param] === '1';
    }

    private static function pluginProperties(): ?PluginProperties {
        $pluginService = Loader::service(Services::PLUGIN);
        $properties = $pluginService->getRoutePluginProperties(self::PLUGIN_MODULE);
        return $properties instanceof PluginProperties ? $properties : null;
    }
}
