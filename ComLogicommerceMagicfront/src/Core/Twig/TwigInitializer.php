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

    /** pId of the LC page carrying the published chrome blob — see {@see ChromeDocument}. */
    private const CHROME_PAGE_PID = 'mff_CHROME';

    /** Memoized generic mff_CHROME document (fetched at most once per request via genericChrome()). */
    private ?ChromeDocument $genericChromeCache = null;

    private bool $genericChromeLoaded = false;

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
        $headerOn = $properties !== null && $properties->isHeaderOverlayEnabled();
        $footerOn = $properties !== null && $properties->isFooterOverlayEnabled();

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
        $params = new PageParametersGroup();
        $params->setPId(self::CHROME_PAGE_PID);
        $collection = Loader::service(Services::PAGE)->getPages($params);
        if (!$collection instanceof ElementCollection) {
            return null;
        }
        $chromePage = $collection->getItems()[0] ?? null;
        if (!$chromePage instanceof Page) {
            return null;
        }
        return ChromeDocument::fromJson($chromePage->getLanguage()?->getPageContent());
    }

    /**
     * Editor request signal — canvas iframe or a non-empty mfToken URL param. Same test as
     * PluginProperties::isPreviewRequest and MagicfrontTrait's editorMode.
     */
    private static function isEditorRequest(): bool {
        return MagicfrontUtils::isCanvasMode() || !empty($_GET[MagicfrontToken::MF_TOKEN]);
    }

    private static function pluginProperties(): ?PluginProperties {
        $pluginService = Loader::service(Services::PLUGIN);
        $properties = $pluginService->getRoutePluginProperties(self::PLUGIN_MODULE);
        return $properties instanceof PluginProperties ? $properties : null;
    }
}
