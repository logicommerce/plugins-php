<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use FWK\Core\Controllers\Controller;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeAssets;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeDocument;
use Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Dtos\Catalog\Page\Page;

/**
 * This class gives the storefront its header, footer and panels: what is published.
 *
 * New pages: mff_HEADER (header and the three panels) and mff_FOOTER (footer).
 * Old pages: mff_CHROME (header and footer) and mff_PANELS (the three panels).
 * Each part is read from its new page and, when the new page does not have it, from its old page.
 * The four pages are loaded together in one call, once per request.
 *
 * @see TwigInitializer
 * @see EditorChrome
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class PublishedChrome {

    private const PLUGIN_MODULE = 'com.logicommerce.magicfront';

    private const PAGES = [SpecialPagePId::HEADER, SpecialPagePId::FOOTER, SpecialPagePId::CHROME, SpecialPagePId::PANELS];

    /** The BO settings of the plugin; null when the plugin has none. */
    private ?PluginProperties $properties;

    private array $documents = [];

    /**
     * Constructor.
     *
     * @param PluginProperties|NULL $properties
     */
    public function __construct(?PluginProperties $properties) {
        $this->properties = $properties;
    }

    /**
     * Returns the published chrome of the commerce, with the plugin's BO settings.
     *
     * @return PublishedChrome
     */
    public static function create(): self {
        $properties = Loader::service(Services::PLUGIN)->getRoutePluginProperties(self::PLUGIN_MODULE);
        return new self($properties instanceof PluginProperties ? $properties : null);
    }

    /**
     * Returns whether a visitor sees our header (or footer): it is switched on in BO and published.
     *
     * @param ChromeKind $kind
     *
     * @return bool
     */
    public function isShown(ChromeKind $kind): bool {
        return $this->isSwitchedOn($kind) && $this->isPublished($kind);
    }

    /**
     * Returns whether our header (or footer) is switched on in BO.
     *
     * @param ChromeKind $kind
     *
     * @return bool
     */
    public function isSwitchedOn(ChromeKind $kind): bool {
        if ($this->properties === null) {
            return false;
        }
        if ($kind === ChromeKind::Footer) {
            return $this->properties->isFooterOverlayEnabled();
        }
        return $this->properties->isHeaderOverlayEnabled();
    }

    /**
     * Returns whether the header (in mff_HEADER or mff_CHROME) or the footer (in mff_FOOTER or mff_CHROME) is published.
     *
     * @param ChromeKind $kind
     *
     * @return bool
     */
    public function isPublished(ChromeKind $kind): bool {
        if ($kind === ChromeKind::Footer) {
            return $this->isAnyPublished(SpecialPagePId::FOOTER, SpecialPagePId::CHROME);
        }
        return $this->isAnyPublished(SpecialPagePId::HEADER, SpecialPagePId::CHROME);
    }

    /**
     * Returns the panels a visitor gets: the one asked for alone, or the three when our header is shown and the panels
     * are published (in mff_HEADER or mff_PANELS).
     *
     * @param bool $headerOn
     * @param ChromeKind|NULL $panelOnly
     *
     * @return ChromeKind[]
     */
    public function getPanels(bool $headerOn, ?ChromeKind $panelOnly): array {
        if ($panelOnly !== null) {
            return [$panelOnly];
        }
        if (!$headerOn || !$this->isAnyPublished(SpecialPagePId::HEADER, SpecialPagePId::PANELS)) {
            return [];
        }
        return ChromeKind::panels();
    }

    /**
     * Returns the header/footer of the page itself, when the page is a MagicFront page that has its own (home, magic
     * page). Null for the other pages (category, product…), whose controllerItem is not a Page.
     *
     * @param array $controllerData
     *
     * @return ChromeDocument|NULL
     */
    public static function getPageChrome(array $controllerData): ?ChromeDocument {
        $page = $controllerData[Controller::CONTROLLER_ITEM] ?? null;
        if (!$page instanceof Page) {
            return null;
        }
        return ChromeDocument::fromJson($page->getLanguage()?->getPageContent());
    }

    /**
     * Returns the header or the footer: the page's own when it has one, else the published one.
     *
     * @param ChromeKind $kind
     * @param ChromeDocument|NULL $pageChrome
     *
     * @return ChromeAssets
     */
    public function getRegion(ChromeKind $kind, ?ChromeDocument $pageChrome): ChromeAssets {
        return self::getAssets($kind, $this->getRegionDocument($kind, $pageChrome));
    }

    /**
     * Returns a published panel.
     *
     * @param ChromeKind $kind
     *
     * @return ChromeAssets
     */
    public function getPanel(ChromeKind $kind): ChromeAssets {
        return self::getAssets($kind, $this->getDocument($kind));
    }

    /**
     * Returns the document of a region: the page's own when it has that region, else the published one.
     *
     * @param ChromeKind $kind
     * @param ChromeDocument|NULL $pageChrome
     *
     * @return ChromeDocument|NULL
     */
    public function getRegionDocument(ChromeKind $kind, ?ChromeDocument $pageChrome): ?ChromeDocument {
        if ($pageChrome !== null && $pageChrome->hasKind($kind)) {
            return $pageChrome;
        }
        return $this->getDocument($kind);
    }

    /**
     * Returns the published document that has the given part, or null when no page has it.
     *
     * @param ChromeKind $kind
     *
     * @return ChromeDocument|NULL
     */
    public function getDocument(ChromeKind $kind): ?ChromeDocument {
        if ($kind === ChromeKind::Footer) {
            $pIds = [SpecialPagePId::FOOTER, SpecialPagePId::CHROME];
        } elseif ($kind->isPanel()) {
            $pIds = [SpecialPagePId::HEADER, SpecialPagePId::PANELS];
        } else {
            $pIds = [SpecialPagePId::HEADER, SpecialPagePId::CHROME];
        }
        foreach ($pIds as $pId) {
            $document = $this->getPage($pId);
            if ($document !== null && $document->hasKind($kind)) {
                return $document;
            }
        }
        return null;
    }

    private static function getAssets(ChromeKind $kind, ?ChromeDocument $document): ChromeAssets {
        if ($document === null) {
            return ChromeAssets::empty();
        }
        return ChromeAssets::fromWidgets($document->widgetsFor($kind), $document->templatesById());
    }

    private function isAnyPublished(string ...$pIds): bool {
        if ($this->properties === null) {
            return false;
        }
        PluginProperties::loadSpecialPages(self::PAGES);
        foreach ($pIds as $pId) {
            if ($this->properties->pageExists($pId)) {
                return true;
            }
        }
        return false;
    }

    private function getPage(string $pId): ?ChromeDocument {
        if (!array_key_exists($pId, $this->documents)) {
            PluginProperties::loadSpecialPages(self::PAGES);
            $page = PluginProperties::loadSpecialPage($pId);
            $this->documents[$pId] = $page === null ? null : ChromeDocument::fromJson($page->getLanguage()?->getPageContent());
        }
        return $this->documents[$pId];
    }
}
