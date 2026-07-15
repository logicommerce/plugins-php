<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use FWK\Core\Controllers\Controller;
use FWK\Core\FilterInput\FilterInput;
use FWK\Enums\Parameters;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetAssetsBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Enums\MagicfrontControllerData;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Resources\Environment;
use SDK\Dtos\Catalog\Page\Page;
use SDK\Dtos\Common\Route;

/**
 * Mixes MagicFront-aware batch/data hooks into HTML controllers (Home,
 * Page\Page). Two paths, dispatched by isEditor() — true iff the request
 * is the dcseditor canvas iframe (Sec-Fetch-Dest: iframe) OR carries a
 * non-empty mfToken in the query:
 *
 *   Editor path     — widgets + templates come from dcsapi via WidgetsService.
 *
 *   Storefront path — regular customer page view. Reads the published blob
 *                     from controllerItem's pageContent (the LC FOB page
 *                     already loaded by FWK). ZERO dcsapi calls — customers
 *                     are unauthenticated for that API.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait MagicfrontTrait {

    public const MFF_PREVIEW = 'mff_preview';

    protected ?ElementCollection $pages = null;

    protected ?WidgetsService $widgetsService = null;

    protected ?Route $route = null;

    protected ?string $pageId = null;

    protected bool $editorMode = false;

    /**
     * Flat list of WidgetInstance for the current page. Populated by either
     * loadStorefrontData (from the blob) or setMagicfrontBatchData (from
     * dcsapi). Consumed by emitMagicfrontData to build inline CSS/JS.
     *
     * @var WidgetInstance[]
     */
    protected array $widgets = [];

    /**
     * The page's chrome doc-id refs `{header:<id>, footer:<id>}`. Populated in the editor
     * path from the page record; consumed by emitMagicfrontData → controllerData so
     * TwigInitializer can fetch each chrome doc by id (empty kinds fall back to defaults).
     *
     * @var array{header?: string, footer?: string}
     */
    protected array $pageChrome = [];

    // ─── FWK lifecycle ─────────────────────────────────────────────────────

    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            MagicfrontToken::MF_TOKEN => new FilterInput($noMod),
            Parameters::PAGE         => new FilterInput($noMod),
            self::MFF_PREVIEW        => new FilterInput($noMod),
        ];
    }

    protected function magicfrontInit(Route $route): void {
        $this->route = $route;

        $rawToken = $this->getRequestParam(MagicfrontToken::MF_TOKEN, false, null);
        $this->editorMode = MagicfrontUtils::isCanvasMode() || !empty($rawToken);

        if (!$this->editorMode) {
            return;
        }

        MagicfrontToken::setToken($rawToken);
        $this->widgetsService = WidgetsService::getInstance();
        $this->pageId = $this->getRequestParam(Parameters::PAGE, false, null)
            ?? $this->widgetsService->getPageId((string)$route->getId());
    }

    // ─── Batch / data hooks ────────────────────────────────────────────────

    protected function setMagicfrontBatchData(BatchRequests $requests): void {
        if (!$this->isEditor() || !$this->pageId) {
            return;
        }
        $instances       = $this->widgetsService->getPageWidgetInstances($this->pageId, $this->route->getLanguage());
        $this->widgets   = WidgetTypeCollector::flatten($instances);
        $this->pages     = WidgetToPageTransformer::transform(new ElementCollection(['items' => $instances]));
        $this->pageChrome = $this->widgetsService->getPageChromeRefs($this->pageId);
    }

    protected function setMagicfrontData(): void {
        $templates = $this->isEditor()
            ? $this->loadEditorTemplates()
            : $this->loadStorefrontData();
        $this->emitMagicfrontData($templates);
    }

    // ─── Editor path (dcsapi) ──────────────────────────────────────────────

    /**
     * $this->widgets is already populated by setMagicfrontBatchData; the editor
     * path only needs the matching widget templates from dcsapi.
     *
     * @return array<string, WidgetTemplate>
     */
    private function loadEditorTemplates(): array {
        $types = WidgetTypeCollector::fromWidgets($this->widgets);
        return $types !== []
            ? $this->widgetsService->getWidgetTemplatesForTypes($types)
            : [];
    }

    // ─── Storefront path (LC FOB blob) ─────────────────────────────────────

    /**
     * The LC page was already loaded by FWK (controllerItem); its pageContent
     * carries the published blob with both widgets and templates. NO request
     * to dcsapi — customers are unauthenticated for that API.
     *
     * Side-effects: sets $this->pages and $this->pageId from the blob.
     *
     * @return array<string, WidgetTemplate>
     */
    private function loadStorefrontData(): array {
        $pageDto = $this->getControllerData(Controller::CONTROLLER_ITEM);
        if (!$pageDto instanceof Page) {
            return [];
        }
        $document = PageDocument::fromJson($pageDto->getLanguage()?->getPageContent());
        if ($document === null) {
            return [];
        }
        $this->widgets = WidgetTypeCollector::flatten($document->widgets()?->getItems() ?? []);
        $this->pages   = $document->toPages();
        $this->pageId  = (string) $pageDto->getId();
        return $document->templatesById();
    }

    // ─── Shared output ─────────────────────────────────────────────────────

    /**
     * @param array<string, WidgetTemplate> $templates
     */
    private function emitMagicfrontData(array $templates): void {
        $this->pages = PageRelationResolver::setData($this->pages);
        $this->setDataValue(PageRelationResolver::PAGES, $this->pages);

        $widgetTypes = WidgetTypeCollector::fromWidgets($this->widgets);

        $widgetTemplateList = [];
        foreach ($templates as $type => $template) {
            $widgetTemplateList[$type] = $template->getTemplateHtml();
        }

        $canvasMode = MagicfrontUtils::isCanvasMode();
        $previewMode = !empty($this->getRequestParam(self::MFF_PREVIEW, false, null));
        $showAssets = (!$canvasMode || $previewMode) && !empty($this->pageId) && !empty($widgetTypes);
        $assets = $showAssets
            ? (new WidgetAssetsBuilder())->build($this->widgets, $templates)
            : ['css' => '', 'js' => ''];

        $this->setDataValue(MagicfrontControllerData::WIDGET_TEMPLATE_LIST, $widgetTemplateList);
        $this->setDataValue(MagicfrontControllerData::WIDGET_TYPES, $widgetTypes);
        $this->setDataValue(MagicfrontControllerData::PAGE, $this->pageId);
        $this->setDataValue(MagicfrontControllerData::PAGE_CHROME, $this->pageChrome);
        $this->setDataValue(MagicfrontControllerData::ASSETS_URL, Environment::get('MF_ASSETS_URL'));
        $this->setDataValue(MagicfrontControllerData::CANVAS_MODE, $canvasMode);
        $this->setDataValue(MagicfrontControllerData::PREVIEW_MODE, $previewMode);
        $this->setDataValue(MagicfrontControllerData::SHOW_ASSETS, $showAssets);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_CSS, $assets['css']);
        $this->setDataValue(MagicfrontControllerData::CUSTOM_JS, $assets['js']);
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /**
     * Editor mode is decided once in magicfrontInit() — see $editorMode for
     * the signals (Sec-Fetch-Dest: iframe OR a non-empty mfToken on the URL).
     */
    private function isEditor(): bool {
        return $this->editorMode;
    }

    protected function isCacheable(): bool {
        if (MagicfrontUtils::isCanvasMode()) {
            return false;
        }
        return parent::isCacheable();
    }

}
