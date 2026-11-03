<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Twig\PublishedChrome;
use Plugins\ComLogicommerceMagicfront\Core\Providers\ProviderRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetLocator;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Core\Services\RouteResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\FunctionType;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Dtos\Catalog\Page\Page as SdkPage;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * `widgetContent` — the generic account-widget filter/sort/pagination endpoint, replacing LC's native
 * per-section reloads (which re-fetched the whole store page). One endpoint for every account widget
 * that supports live reload (orders, shoppingList, …).
 *
 * STOREFRONT-SAFE: reads the PUBLISHED blob of the page the widget was rendered on — the page is recovered
 * from the request Referer (see {@see resolveBlobPId}), so NO client-sent pId is needed and account widgets
 * work on ANY page (account area, landing/PAGE, …). Loads the blob via the LC FOB page service — NO
 * dcsapi/Bearer, so logged-in customers (not just the editor) can use it. Finds the placed widget in
 * the blob by id, re-fetches the widget's data for the current request's params ($_GET) via
 * {@see DataWidgetRegistry}, and renders it. Returns the widget HTML; the widget's templateJs
 * extracts its own reload container and swaps it in place — no full-page reload.
 *
 * With `chrome=<kind>` ({@see FunctionType::CHROME_PARAM}) the widget is looked for in the PUBLISHED chrome of that kind
 * instead ({@see PublishedChrome}): the basket panel's pieces repaint themselves that way after every change of the
 * basket, on whatever page the panel was opened.
 *
 * Extends {@see GetWidgetHandler} only to reuse its Twig-environment build + render helpers. The
 * per-widget data step lives in {@see DataWidgetRegistry}, shared with the full-page render, so any
 * account data widget reloads with no per-widget handler.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers
 */
class WidgetContentHandler extends GetWidgetHandler {

    private const LOOKUP_KEY = 'mffWidgetContentLookup';

    public function supports(string $type): bool {
        return $type === FunctionType::WIDGET_CONTENT;
    }

    /**
     * STOREFRONT reload — never the canvas. Without this the handler would inherit
     * {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\GetWidgetHandler}'s
     * canvas default and every reloaded widget would render its `mff_isCanvasMode()` mock, replacing the
     * visitor's real rows with placeholders.
     */
    protected function rendersCanvas(): bool {
        return false;
    }

    public function getRawResponseContentType(): ?string {
        return 'text/html; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $widgetId = (string) ($controller->getRequestParamValue(Parameters::WIDGET_ID, false) ?? '');
        // A piece of the published chrome (the basket panel's, repainted after every change of the basket): read from
        // the published document of that kind, never from the page the request came from.
        $chrome = (string) ($controller->getRequestParamValue(FunctionType::CHROME_PARAM, false) ?? '');
        if ($chrome !== '') {
            try {
                return $this->renderFromChrome($controller, $chrome, $widgetId);
            } catch (\Throwable $e) {
                return '';
            }
        }
        // Prefer the client-sent pId (widget's data-mff-page-pid, so the widget works on ANY page even
        // when the Referer is stripped); fall back to recovering it from the request Referer.
        $pId      = (string) ($controller->getRequestParamValue(Parameters::P_ID, false) ?? '');
        if ($pId === '') {
            $pId = $this->resolveBlobPId();
        }
        if ($pId === '') {
            return '';
        }

        try {
            $document = $this->loadBlob($pId);
            if ($document === null) {
                return '';
            }
            // The lazy account panel loads its whole content SUBTREE here — the container the panel holds
            // (a `group`) plus the real account widget inside it. Walk the subtree once: fetch the data of
            // every DataWidgetRegistry-typed node (e.g. the `orders` inside the group) and collect every
            // type present, so the render has all templates. Rendering the root (the group) then renders
            // its children through the widget macro. Works for a plain data widget too (subtree = itself).
            return $this->renderIds($controller, $document->toPages(), $document->templatesById(), $widgetId);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * `chrome=<kind>`: the widget(s) of the PUBLISHED chrome of that kind ({@see PublishedChrome}: mff_HEADER when it
     * carries the kind, else the legacy mff_PANELS / mff_CHROME; mff_FOOTER for the footer). '' for an unknown kind, a
     * kind nobody published, or a widget it does not hold.
     */
    private function renderFromChrome(ComLogicommerceMagicfrontController $controller, string $chrome, string $widgetId): string {
        $source = $this->chromeSource($chrome);
        return $source === null ? '' : $this->renderIds($controller, $source[0], $source[1], $widgetId);
    }

    /**
     * The widgets (as the Page tree the render walks) and the templates of the published chrome of kind `$chrome`, or
     * null for an unknown kind or one nobody published.
     *
     * @return array{0: ?ElementCollection, 1: array}|null
     */
    private function chromeSource(string $chrome): ?array {
        $kind = ChromeKind::tryFrom($chrome);
        if ($kind === null) {
            return null;
        }
        // No BO settings: they only gate what a visitor sees, and this reads a document already painted.
        $document = (new PublishedChrome(null))->getDocument($kind);
        if ($document === null) {
            return null;
        }
        return [WidgetToPageTransformer::transform($document->widgetsFor($kind)), $document->templatesById()];
    }

    /**
     * One widget id (bare HTML, as always) or a comma-separated list ({@see renderBatch()}), found among `$pages` and
     * rendered with `$templates` (the document's schema by template id).
     */
    private function renderIds(ComLogicommerceMagicfrontController $controller, ?ElementCollection $pages, array $templates,
        string $widgetId): string {
        $widgetIds = array_values(array_filter(array_map('trim', explode(',', $widgetId))));
        if (count($widgetIds) > 1) {
            return $this->renderBatch($controller, $pages, $templates, $widgetIds);
        }
        $widget = WidgetLocator::findById($pages, $widgetId);
        if ($widget === null) {
            return '';
        }
        return $this->renderOne($controller, $templates, $widget);
    }

    /**
     * Render SEVERAL widgets of the same page in ONE request.
     *
     * A checkout recalculate refreshes every MFF-rendered module at once. One request per module meant N
     * framework bootstraps and N {@see self::loadBlob()} round-trips for the SAME blob — the dominant cost
     * of the reload, and the reason the modules finished seconds apart. The orchestrator
     * (onePageCheckout's `osc.refresh`) now sends the whole set as a comma-separated `widgetId`, and each
     * fragment comes back wrapped in `<div data-mff-widget="ID">` so the client can hand every module the
     * HTML its own `reload()` would have fetched. A single id keeps the historic bare-HTML response, so a
     * widget refreshing on its own is untouched. The basket panel's pieces ask the same way (`chrome=basketPanel`).
     *
     * @param string[] $widgetIds
     */
    private function renderBatch(
        ComLogicommerceMagicfrontController $controller,
        ?ElementCollection $pages,
        array $templates,
        array $widgetIds
    ): string {
        $out   = '';
        foreach ($widgetIds as $id) {
            $widget = WidgetLocator::findById($pages, $id);
            if ($widget === null) {
                continue;
            }
            $html = $this->renderOne($controller, $templates, $widget);
            $out .= '<div data-mff-widget="' . htmlspecialchars($id, ENT_QUOTES, \CHARSET) . '">' . $html . '</div>';
        }
        return $out;
    }

    /**
     * The data-fetch + render of ONE placed widget, shared by the single and the batched response.
     *
     * @param array $templates the document's widget templates by template id
     */
    private function renderOne(
        ComLogicommerceMagicfrontController $controller,
        array $templates,
        PluginPage $widget
    ): string {
        $subtree = [];
        $this->collectSubtree($widget, $subtree);
        $types = [];
        $pieces = [];
        $fetched = [];
        foreach ($subtree as $node) {
            $family = (string) $node->getCustomType();
            if ($family === '') {
                continue;
            }
            $types[$node->getTemplateKey()] = true;
            $dataType = DataWidgetRegistry::dataTypeOfPiece($family);
            if ($dataType !== null) {
                $pieces[] = [$node, $family, $dataType];
            } elseif (DataWidgetRegistry::handles($family)) {
                // Each data widget fetches its own, as before (its own page size); the first one serves the pieces.
                $assignments = DataWidgetRegistry::fetchInto(new ElementCollection(['items' => [$node]]), $family, $node);
                $fetched[$family] ??= $assignments;
            }
        }
        // A piece that reads a data widget's data (the account Summary's, E22) reuses that widget's fetch when it
        // is in the same subtree, and asks once otherwise.
        foreach ($pieces as [$node, $family, $dataType]) {
            $items = new ElementCollection(['items' => [$node]]);
            if (isset($fetched[$dataType])) {
                DataWidgetRegistry::attach($items, $fetched[$dataType]);
            } else {
                $fetched[$dataType] = DataWidgetRegistry::fetchInto($items, $family, $node);
            }
        }
        if ($types === []) {
            return '';
        }
        $widgetTemplateList = $this->buildWidgetTemplateList(array_keys($types), $templates);
        ProviderRegistry::attachReferenced(new ElementCollection(['items' => [$widget]]), $widgetTemplateList);

        return $this->renderWidget($controller, $widget, $widgetTemplateList);
    }

    /** Depth-first flatten of a placed-widget subtree: the node plus every descendant subpage. */
    private function collectSubtree(PluginPage $node, array &$out): void {
        $out[] = $node;
        foreach (($node->getSubpages() ?? []) as $sp) {
            if ($sp instanceof PluginPage) {
                $this->collectSubtree($sp, $out);
            }
        }
    }

    /**
     * The blob the widget lives in, recovered WITHOUT a client-sent pId: resolve the page it was fired
     * from via the request Referer → LC route API → Route, then map that route to its blob identifier the
     * same way the page render does — a SINGLETON route maps to its stable `mff_*` pId (see
     * {@see SpecialPagePId::forRouteType} → {@see \PublishClientImpl} on the dcsapi side); a PAGE/landing
     * route has no mff_ pId, so it is addressed by the route's numeric page id.
     */
    private function resolveBlobPId(): string {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer === '') {
            return '';
        }
        $route = RouteResolver::forPath($referer);
        if (!$route instanceof Route) {
            return '';
        }
        return SpecialPagePId::forRouteType((string) $route->getType()) ?? (string) $route->getId();
    }

    /**
     * Fetch + decode the published blob for the given page identifier (no dcsapi/Bearer — storefront FOB).
     * Mirrors {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait::magicfrontPage()}:
     * the singleton mff_* pages are addressable only by their stable pId ({@see PageParametersGroup::setPId} +
     * getPages), while per-page (PAGE/landing) blobs are loaded by their numeric page id ({@see addGetPage} —
     * the same load BasePageController uses for every public page). The identifier's shape selects the branch.
     */
    private function loadBlob(string $pId): ?PageDocument {
        $requests = new BatchRequests();
        if (ctype_digit($pId)) {
            Loader::service(Services::PAGE)->addGetPage($requests, self::LOOKUP_KEY, (int) $pId);
        } else {
            $params = new PageParametersGroup();
            $params->setPId($pId);
            Loader::service(Services::PAGE)->addGetPages($requests, self::LOOKUP_KEY, $params);
        }
        $result  = BatchService::getInstance()->send($requests);
        $res     = $result[self::LOOKUP_KEY] ?? null;
        $pageDto = $res instanceof ElementCollection ? ($res->getItems()[0] ?? null) : $res;
        if (!$pageDto instanceof SdkPage) {
            return null;
        }
        return PageDocument::fromJson($pageDto->getLanguage()?->getPageContent());
    }
}
