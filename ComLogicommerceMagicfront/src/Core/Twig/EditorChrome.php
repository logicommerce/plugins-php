<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeAssets;
use Plugins\ComLogicommerceMagicfront\Dtos\Chrome\ChromeDocument;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;

/**
 * This class builds the header, the footer and the panels the editor paints (canvas and preview tab).
 * The storefront never uses it: it paints what is published (see PublishedChrome).
 *
 * @see TwigInitializer
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class EditorChrome {

    /** Studio: one widget alone, without header and footer. */
    public const STUDIO_LAYOUT = 'layouts/mff-studio.html.twig';

    private const PANEL_ONLY_LAYOUT = 'layouts/mff-chrome-only-panel.html.twig';

    private const REGION_ONLY_LAYOUT_PREFIX = 'layouts/mff-chrome-only-';

    /** URL params of the preview tab with the toolbar choice ('1' = ours, '0' = the store's). Only read with mfToken. */
    private const PREVIEW_HEADER_PARAM = 'mfHeader';

    private const PREVIEW_FOOTER_PARAM = 'mfFooter';

    private string $language;

    /** The header/footer document each page points at (page.chrome), by kind. */
    private array $pageChromeIds;

    /** The region Personalización edits alone (?mff_chrome_only=), or null. */
    private ?ChromeKind $chromeOnly;

    /** The draft «Ver cómo queda» shows in place of the region being edited (E20), or null. */
    private ?string $draftId;

    /** Current templates of the panel roots, by published document (see getCurrentPanelTemplates()). */
    private array $currentPanelTemplates = [];

    /**
     * Constructor.
     *
     * @param string $language 2-letter ISO code: magicfront-backend compares the language exactly
     * @param array $pageChromeIds
     * @param ChromeKind|NULL $chromeOnly
     * @param string|NULL $draftId
     */
    public function __construct(string $language, array $pageChromeIds, ?ChromeKind $chromeOnly, ?string $draftId) {
        $this->language = $language;
        $this->pageChromeIds = $pageChromeIds;
        $this->chromeOnly = $chromeOnly;
        $this->draftId = $draftId;
    }

    /**
     * Returns the editor's builder for this request: the page's documents (page.chrome) and the draft of «Ver cómo
     * queda».
     *
     * @param string|NULL $routeLanguage
     * @param array $controllerData
     * @param ChromeKind|NULL $chromeOnly
     *
     * @return EditorChrome
     */
    public static function create(?string $routeLanguage, array $controllerData, ?ChromeKind $chromeOnly): self {
        $pageChromeIds = $controllerData[MagicfrontControllerData::PAGE_CHROME] ?? [];
        if (!is_array($pageChromeIds)) {
            $pageChromeIds = [];
        }
        return new self(MagicfrontUtils::contentLanguage($routeLanguage), $pageChromeIds, $chromeOnly,
            MagicfrontUtils::chromeDraftId());
    }

    /**
     * Returns whether the request is the Studio document: one store widget alone.
     *
     * @param array $controllerData
     *
     * @return bool
     */
    public static function isStudioDocument(array $controllerData): bool {
        return !empty($controllerData[MagicfrontControllerData::STUDIO_DOCUMENT]);
    }

    /**
     * Returns the region Personalización edits alone (?mff_chrome_only=), or null.
     *
     * @return ChromeKind|NULL
     */
    public static function getChromeOnly(): ?ChromeKind {
        return MagicfrontUtils::chromeOnlyRegion();
    }

    /**
     * Returns the panel Personalización edits alone, or null. A panel is shown with the header behind it.
     *
     * @param ChromeKind|NULL $chromeOnly
     *
     * @return ChromeKind|NULL
     */
    public static function getPanelOnly(?ChromeKind $chromeOnly): ?ChromeKind {
        if ($chromeOnly === null || !$chromeOnly->isPanel()) {
            return null;
        }
        return $chromeOnly;
    }

    /**
     * Returns whether our header (or footer) is shown.
     * Canvas: always (the toolbar swaps ours and the store's live). Preview tab: its choice, if published. Otherwise
     * what a visitor sees. Personalización shows the footer alone, or the header (alone or behind a panel).
     *
     * @param ChromeKind $kind
     * @param ChromeKind|NULL $chromeOnly
     * @param PublishedChrome $published
     *
     * @return bool
     */
    public static function isRegionShown(ChromeKind $kind, ?ChromeKind $chromeOnly, PublishedChrome $published): bool {
        if (RenderMode::isCanvasMode()) {
            $shown = true;
        } else {
            $previewChoice = self::getPreviewChoice($kind);
            if ($previewChoice === null) {
                $shown = $published->isShown($kind);
            } else {
                $shown = $previewChoice && $published->isPublished($kind);
            }
        }
        if ($chromeOnly === null) {
            return $shown;
        }
        if ($kind === ChromeKind::Footer) {
            return $shown && $chromeOnly === ChromeKind::Footer;
        }
        return $shown && $chromeOnly !== ChromeKind::Footer;
    }

    /**
     * Returns the layout: the full one, or the one of the region Personalización edits alone (the three panels share
     * one).
     *
     * @param string $layout the full layout
     * @param ChromeKind|NULL $chromeOnly
     *
     * @return string
     */
    public static function getLayout(string $layout, ?ChromeKind $chromeOnly): string {
        if ($chromeOnly === null) {
            return $layout;
        }
        if (self::getPanelOnly($chromeOnly) !== null) {
            return self::PANEL_ONLY_LAYOUT;
        }
        return self::REGION_ONLY_LAYOUT_PREFIX . $chromeOnly->value . '.html.twig';
    }

    /**
     * Returns the panels the editor paints: the one Personalización edits alone, or the three when our header is shown.
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
        return $headerOn ? ChromeKind::panels() : [];
    }

    /**
     * Returns the header or the footer from magicfront-backend: the draft «Ver cómo queda» shows, else the document
     * of the page, else the commerce default.
     *
     * @param ChromeKind $kind
     *
     * @return ChromeAssets
     */
    public function getRegion(ChromeKind $kind): ChromeAssets {
        if ($this->draftId !== null && $this->chromeOnly === $kind) {
            return $this->getFromApi($kind, $this->draftId);
        }
        return $this->getFromApi($kind, $this->pageChromeIds[$kind->value] ?? null);
    }

    /**
     * Returns a panel from magicfront-backend, as the header: the one Personalización edits alone (the draft «Ver cómo
     * queda» shows, else its document), and the ones next to the header (the page's document, else the commerce's).
     * The editor and its preview show what the merchant is editing, never what is published: next to a draft header,
     * the published panels painted another version, or an empty one while the publication was half done.
     * A panel with no document in magicfront-backend falls back to the published one (see getPublishedPanel()).
     *
     * @param ChromeKind $kind
     * @param PublishedChrome $published
     *
     * @return ChromeAssets
     */
    public function getPanel(ChromeKind $kind, PublishedChrome $published): ChromeAssets {
        if ($this->chromeOnly === $kind) {
            return $this->getFromApi($kind, $this->draftId);
        }
        $draft = $this->getFromApi($kind, $this->pageChromeIds[$kind->value] ?? null);
        if ($draft->pages !== null && count($draft->pages->getItems()) > 0) {
            return $draft;
        }
        return $this->getPublishedPanel($kind, $published->getDocument($kind));
    }

    /**
     * Returns a published panel as the editor paints it next to the header.
     * The published document keeps the CSS of the day it was published, so each root anchored to a version takes its
     * current template (#6270); its inner widgets keep the published one. Each panel gets only the templates it uses,
     * so it cannot carry the CSS of another panel or of the header (#6408).
     *
     * @param ChromeKind $kind
     * @param ChromeDocument|NULL $document the published document that has the panel
     *
     * @return ChromeAssets
     */
    public function getPublishedPanel(ChromeKind $kind, ?ChromeDocument $document): ChromeAssets {
        $roots = $document?->widgetsFor($kind);
        if ($roots === null) {
            return ChromeAssets::empty();
        }
        $templates = $this->getCurrentPanelTemplates($document) + $document->templatesById();
        return ChromeAssets::fromWidgets($roots, self::getUsedTemplates($roots->getItems(), $templates));
    }

    /**
     * Returns the choice of the preview tab for a region: true for ours, false for the store's, null when there is none.
     * Only with the preview token, so a visitor can never change the header from the URL.
     *
     * @param ChromeKind $kind
     *
     * @return bool|NULL
     */
    public static function getPreviewChoice(ChromeKind $kind): ?bool {
        $param = $kind === ChromeKind::Footer ? self::PREVIEW_FOOTER_PARAM : self::PREVIEW_HEADER_PARAM;
        if (MagicfrontToken::getToken() === null || !isset($_GET[$param])) {
            return null;
        }
        return $_GET[$param] === '1';
    }

    /**
     * Returns the variant a canvas region starts with: '1' (ours) unless the editor asked for the store's.
     * Personalización always shows ours. Never the BO switch: that one is for the visitors.
     *
     * @param ChromeKind $kind
     * @param ChromeKind|NULL $chromeOnly
     *
     * @return string
     */
    public static function getInitialVariant(ChromeKind $kind, ?ChromeKind $chromeOnly): string {
        if ($chromeOnly === $kind) {
            return '1';
        }
        return (self::getPreviewChoice($kind) ?? true) ? '1' : '0';
    }

    /**
     * Returns the document with the given id from magicfront-backend, or the commerce default of the kind when there is
     * no id. Templates are asked by the version each widget is anchored to (header@2…).
     */
    private function getFromApi(ChromeKind $kind, ?string $chromeId): ChromeAssets {
        $service = WidgetsService::getInstance();
        if ($chromeId !== null && $chromeId !== '') {
            $widgets = $service->getChromeDoc($chromeId, $this->language);
        } else {
            $widgets = $service->getChromeDoc($kind->value, $this->language, true);
        }
        if (empty($widgets)) {
            return ChromeAssets::empty();
        }
        $types = WidgetTypeCollector::templateKeysFromWidgets(WidgetTypeCollector::flatten($widgets));
        $templates = empty($types) ? [] : $service->getWidgetTemplatesForTypes($types);
        return ChromeAssets::fromWidgets(new ElementCollection(['items' => $widgets]), $templates);
    }

    /**
     * Returns only the templates the given widgets use (by versioned key, or else by family).
     *
     * @param WidgetInstance[] $widgets
     * @param array $templates
     *
     * @return array
     */
    private static function getUsedTemplates(array $widgets, array $templates): array {
        $used = [];
        $keys = WidgetTypeCollector::templateKeysFromWidgets(WidgetTypeCollector::flatten($widgets));
        foreach ($keys as $key) {
            if (!array_key_exists($key, $templates)) {
                $key = WidgetTypeCollector::familyOf($key);
            }
            if (array_key_exists($key, $templates)) {
                $used[$key] = $templates[$key];
            }
        }
        return $used;
    }

    /**
     * Returns the current template of each panel root anchored to a version (accountPanel@2…), by its key.
     * One call for the three panels of a document. Only anchored roots: a bare family would get its latest version.
     */
    private function getCurrentPanelTemplates(ChromeDocument $document): array {
        $id = spl_object_id($document);
        if (isset($this->currentPanelTemplates[$id])) {
            return $this->currentPanelTemplates[$id];
        }
        $anchored = [];
        foreach (ChromeKind::panels() as $kind) {
            foreach (WidgetTypeCollector::templateKeysFromWidgets($document->widgetsFor($kind)?->getItems() ?? []) as $key) {
                if (str_contains($key, '@')) {
                    $anchored[$key] = $key;
                }
            }
        }
        $current = [];
        if (!empty($anchored)) {
            try {
                $current = WidgetsService::getInstance()->getWidgetTemplatesForTypes(array_values($anchored));
            } catch (\Throwable) {
                $current = [];
            }
        }
        $this->currentPanelTemplates[$id] = $current;
        return $current;
    }
}
