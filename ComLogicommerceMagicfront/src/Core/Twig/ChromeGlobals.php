<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Providers\SharedDataProvider;
use Plugins\ComLogicommerceMagicfront\Core\Providers\SharedNeeds;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeAssets;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Enums\PluginCapability;
use Twig\Environment;

/**
 * This class puts our header, footer and panels into the Twig globals the layout reads (magicfront.html.twig).
 * Used the same way by the editor and by the storefront.
 *
 * @see TwigInitializer
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class ChromeGlobals {

    /** Twig globals: the variant each region starts with in the canvas ('1' = ours, '0' = the store's). */
    private const HEADER_MFF_GLOBAL = 'mffHeader';

    private const FOOTER_MFF_GLOBAL = 'mffFooter';

    /** Groups of reinjectLcIds: the panels keep their own, so the header and the footer cannot take their ids. */
    private const CHROME_GROUP = 'chrome';

    private const PANELS_GROUP = 'panels';

    private Environment $main;

    /** The panel Personalización edits alone, or null. */
    private ?ChromeKind $panelOnly = null;

    /** Ids already re-injected in this request, by group (see reinjectLcIds()). */
    private array $lcIdsEmitted = [];

    /** Widget types added in this request, for the `shared` they need. */
    private array $chromeTypes = [];

    /** Templates added in this request, for the `shared` they need. */
    private array $chromeTemplates = [];

    /**
     * Constructor.
     *
     * @param Environment $main
     */
    public function __construct(Environment $main) {
        $this->main = $main;
    }

    /**
     * Adds what the layout paints: our header and/or footer, the panel edited alone, and the variant each region starts
     * with in the canvas. In the canvas, also what this plugin can paint (the editor offers only that).
     *
     * @param bool $headerOn
     * @param bool $footerOn
     * @param ChromeKind|NULL $chromeOnly
     * @param ChromeKind|NULL $panelOnly
     */
    public function addRegionFlags(bool $headerOn, bool $footerOn, ?ChromeKind $chromeOnly, ?ChromeKind $panelOnly): void {
        $this->main->addGlobal(self::HEADER_MFF_GLOBAL, EditorChrome::getInitialVariant(ChromeKind::Header, $chromeOnly));
        $this->main->addGlobal(self::FOOTER_MFF_GLOBAL, EditorChrome::getInitialVariant(ChromeKind::Footer, $chromeOnly));
        $this->main->addGlobal(MagicfrontControllerData::OVERRIDE_HEADER, $headerOn);
        $this->main->addGlobal(MagicfrontControllerData::OVERRIDE_FOOTER, $footerOn);
        $this->main->addGlobal(MagicfrontControllerData::PANEL_ONLY, $panelOnly?->value);
        $this->main->addGlobal(MagicfrontControllerData::PLUGIN_CAPABILITIES, RenderMode::isCanvasMode() ? PluginCapability::declared() : '');
        $this->panelOnly = $panelOnly;
    }

    /**
     * Adds the header or the footer.
     *
     * @param ChromeKind $kind
     * @param ChromeAssets $assets
     */
    public function addRegion(ChromeKind $kind, ChromeAssets $assets): void {
        $this->add($kind, $this->reinjectLcIds($assets, self::CHROME_GROUP));
    }

    /**
     * Adds a panel.
     * categoryMenu@3 opens the category dropdown only when it is really in the document: without it (a store whose header
     * was published before the dropdown existed) its bar keeps the dropdown inside each category. The dropdown edited
     * alone always counts: the header behind it is its context.
     *
     * @param ChromeKind $kind
     * @param ChromeAssets $assets
     */
    public function addPanel(ChromeKind $kind, ChromeAssets $assets): void {
        $this->add($kind, $this->reinjectLcIds($assets, self::PANELS_GROUP));
        if ($kind === ChromeKind::CategoryDropdown
            && ($this->panelOnly === $kind || ($assets->pages !== null && count($assets->pages->getItems()) > 0))) {
            $this->main->addGlobal(MagicfrontControllerData::CATEGORY_DROPDOWN_ON, true);
        }
    }

    /**
     * Adds the `shared` data the added header, footer and panels need.
     * A page that is not MagicFront's has no `shared`: one is built here (mffShared). A MagicFront page has its own,
     * built for its templates: what only the chrome reads is added on top (mffChromeShared, #4251).
     *
     * @param array $controllerData
     */
    public function addShared(array $controllerData): void {
        $shared = ProviderRegistry::sharedForChrome($controllerData, $this->chromeTypes, null, SharedNeeds::of($this->chromeTemplates));
        if ($shared !== null) {
            $this->main->addGlobal(MagicfrontControllerData::SHARED, $shared);
        }
        $pageShared = $controllerData[MagicfrontControllerData::SHARED] ?? null;
        if (is_array($pageShared) && !empty($this->chromeTemplates)) {
            $missing = array_values(array_diff(SharedNeeds::of($this->chromeTemplates), array_keys($pageShared)));
            if (!empty($missing)) {
                $this->main->addGlobal(MagicfrontControllerData::CHROME_SHARED, $pageShared + (new SharedDataProvider())->onDemandShared($missing));
            }
        }
    }

    private function add(ChromeKind $kind, ChromeAssets $assets): void {
        array_push($this->chromeTypes, ...array_keys($assets->templateList));
        foreach ($assets->templateList as $template) {
            $this->chromeTemplates[] = $template;
        }
        $this->main->addGlobal($kind->pagesGlobalKey(), $assets->pages);
        $this->main->addGlobal($kind->templatesGlobalKey(), $assets->templateList);
        $this->main->addGlobal($kind->cssGlobalKey(), $assets->css);
        $this->main->addGlobal($kind->jsGlobalKey(), $assets->js);
    }

    /**
     * Puts back the element ids LogiCommerce's JS looks for (#smallLoginOffcanvas, #miniBasketOffcanvas,
     * #mini-basket-button…). The markup only carries data-lc-id. An id must be unique in the document, so each one is
     * injected once per group.
     */
    private function reinjectLcIds(ChromeAssets $assets, string $group): ChromeAssets {
        $emitted = &$this->lcIdsEmitted[$group];
        if (!is_array($emitted)) {
            $emitted = [];
        }
        $templateList = [];
        foreach ($assets->templateList as $type => $html) {
            $templateList[$type] = preg_replace_callback(
                '/data-lc-id="([^"]+)"/',
                function (array $match) use (&$emitted): string {
                    if (isset($emitted[$match[1]])) {
                        return $match[0];
                    }
                    $emitted[$match[1]] = true;
                    return 'id="' . $match[1] . '" ' . $match[0];
                },
                $html
            );
        }
        return new ChromeAssets($assets->pages, $templateList, $assets->css, $assets->js);
    }
}
