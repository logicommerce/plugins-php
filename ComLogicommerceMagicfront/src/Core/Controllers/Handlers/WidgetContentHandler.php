<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers;

use FWK\Core\Resources\Loader;
use FWK\Enums\Parameters;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute\ComLogicommerceMagicfrontController;
use Plugins\ComLogicommerceMagicfront\Core\Providers\DataWidgetRegistry;
use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetLocator;
use Plugins\ComLogicommerceMagicfront\Core\Services\RouteResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
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

    public function getRawResponseContentType(): ?string {
        return 'text/html; charset=' . \CHARSET;
    }

    public function getRawResponseContent(ComLogicommerceMagicfrontController $controller): ?string {
        $widgetId = (string) ($controller->getRequestParamValue(Parameters::WIDGET_ID, false) ?? '');
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
            $widget = WidgetLocator::findById($document->toPages(), $widgetId);
            if ($widget === null) {
                return '';
            }
            // The lazy account panel loads its whole content SUBTREE here — the container the panel holds
            // (a `group`) plus the real account widget inside it. Walk the subtree once: fetch the data of
            // every DataWidgetRegistry-typed node (e.g. the `orders` inside the group) and collect every
            // type present, so the render has all templates. Rendering the root (the group) then renders
            // its children through the widget macro. Works for a plain data widget too (subtree = itself).
            $subtree = [];
            $this->collectSubtree($widget, $subtree);
            $types = [];
            foreach ($subtree as $node) {
                $family = (string) $node->getCustomType();
                if ($family === '') {
                    continue;
                }
                $types[$node->getTemplateKey()] = true;
                if (DataWidgetRegistry::handles($family)) {
                    DataWidgetRegistry::fetchInto(new ElementCollection(['items' => [$node]]), $family, $node);
                }
            }
            if ($types === []) {
                return '';
            }
            $widgetTemplateList = $this->buildWidgetTemplateList(array_keys($types), $document->templatesById());

            return $this->renderWidget($controller, $widget, $widgetTemplateList);
        } catch (\Throwable $e) {
            return '';
        }
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
