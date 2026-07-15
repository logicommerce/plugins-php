<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Services;

use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Services\SuccessCacheTrait;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Enums\Resource;
use SDK\Core\Builders\RequestBuilder;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Request;
use SDK\Core\Resources\Environment;
use SDK\Core\Services\Service;

/**
 * This is the WidgetsService class.
 * Provides access to the Magic Front API for widget data, widget templates,
 * and authentication tokens. Results are transformed into the Page /
 * ElementCollection format expected by the rest of the framework.
 *
 * Note: kept as a plugin-local singleton (private static $instance) because
 * the SDK / FWK Registry classes only accept whitelisted key constants, so
 * a plugin service cannot register through the canonical ServiceTrait.
 *
 * @see WidgetsService::getPageWidgetInstances()
 * @see WidgetsService::getPageWidgetById()
 * @see WidgetsService::getPageId()
 * @see WidgetsService::getWidgetTemplatesForTypes()
 *
 * @package Plugins\ComLogicommerceMagicfront\Services
 */
class WidgetsService extends Service {

    use SuccessCacheTrait;

    private const CACHEABLE_PATH_PREFIXES = [
        Resource::WIDGET_TEMPLATES_BASE,
    ];

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Returns raw WidgetInstance objects for the given page.
     *
     * `$language` is required: the Java endpoint applies locale filtering only when
     * `?language=` is present. Calling without it returns the multi-locale shape
     * `[{language, value}, ...]` which would render as JSON-literal text in Twig.
     * All current callers (storefront route, customize handlers) already have a
     * locale in scope; making the parameter mandatory closes the door so a future
     * caller can't silently break the renderer.
     *
     * @return WidgetInstance[]
     */
    public function getPageWidgetInstances(string $pageId, string $language): array {
        return $this->fetchCollection(
            WidgetInstance::class,
            $this->replaceWildcards(Resource::GET_PAGE_WIDGETS, ['pageId' => $pageId]),
            ['language' => $language]
        )?->getItems() ?? [];
    }

    /**
     * Returns a single widget by ID as the raw WidgetInstance subtree (children +
     * styleValues intact). Callers that need per-instance CSS flatten this via
     * {@see WidgetTypeCollector::flatten}; callers that only render use
     * {@see getPageWidgetById} (the Page-transformed view).
     */
    public function getPageWidgetInstanceById(string $pageId, string $widgetId, string $language): ?WidgetInstance {
        $widget = $this->getResourceElement(
            WidgetInstance::class,
            $this->replaceWildcards(Resource::GET_PAGE_WIDGET_BY_ID, ['pageId' => $pageId, 'widgetId' => $widgetId]),
            ['language' => $language]
        );
        return $widget instanceof WidgetInstance ? $widget : null;
    }

    /**
     * Returns a single widget by ID, transformed into Page format.
     */
    public function getPageWidgetById(string $pageId, string $widgetId, string $language): ?Page {
        $widget = $this->getPageWidgetInstanceById($pageId, $widgetId, $language);
        return $widget !== null
            ? WidgetToPageTransformer::transformSingle($widget)
            : null;
    }

    /**
     * Returns the chrome widget tree for the chrome DOC addressed by its own id
     * (= root widget id). The page points at this id via `page.chrome.{header|footer}`;
     * the same id resolves whether the doc is the shared default or a page-specific fork.
     * No pageId / kind in the fetch path — the id alone selects the doc.
     *
     * When `$asDefault` is true, `$id` is a KIND token ("header"/"footer") and the backend
     * resolves (lazy-seeding if missing) the per-commerce DEFAULT doc of that kind — the
     * dedicated `/commerces/chrome/defaults` endpoint was removed by the doc-ref rework, the
     * default of a kind is now read via `GET /chrome/{kind}?default=true`. The returned tree's
     * root id IS the default doc id.
     *
     * @param string $id        Chrome doc id (root widget id), or a kind token when $asDefault.
     * @param string $language  2-letter ISO language code.
     * @param bool   $asDefault Resolve the commerce default of kind `$id` instead of a doc by id.
     *
     * @return WidgetInstance[]
     */
    public function getChromeDoc(string $id, string $language, bool $asDefault = false): array {
        $urlParams = ['language' => $language];
        if ($asDefault) {
            $urlParams['default'] = 'true';
        }
        return $this->fetchCollection(
            WidgetInstance::class,
            $this->replaceWildcards(Resource::GET_CHROME_DOC, ['id' => $id]),
            $urlParams
        )?->getItems() ?? [];
    }

    /**
     * Returns the `page.chrome` doc-id refs `{header:<id>, footer:<id>}` for a page, read
     * from the page record (`GET /pages/{pageId}`). Empty entries are omitted so the caller
     * can fall back to the commerce default per kind via {@see getChromeDoc()} with `$asDefault`.
     *
     * @return array{header?: string, footer?: string}
     */
    public function getPageChromeRefs(string $pageId): array {
        $data = $this->call(
            (new RequestBuilder())
                ->path($this->replaceWildcards(Resource::GET_PAGE_BY_ID, ['pageId' => $pageId]))
                ->build()
        );
        $chrome = $data['chrome'] ?? null;
        if (!is_array($chrome)) {
            return [];
        }
        $refs = [];
        foreach (['header', 'footer'] as $kind) {
            if (isset($chrome[$kind]) && is_string($chrome[$kind]) && $chrome[$kind] !== '') {
                $refs[$kind] = $chrome[$kind];
            }
        }
        return $refs;
    }

    /**
     * Returns the Magic Front page ID for the given route ID (0 = home page).
     */
    public function getPageId(string $routeId): string {
        // Raw call (no DTO hydration): the plugin Page inherits SDK's `int $id`,
        // but Java pages use string UUIDs, so hydrating as Page would TypeError.
        $data = $this->call(
            (new RequestBuilder())
                ->path(Resource::GET_PAGES)
                ->urlParams(['pageType' => (int) $routeId === 0 ? 'HOME' : 'LANDING'])
                ->build()
        );
        return (string) $data['items'][0]['id'];
    }

    /**
     * Returns templates for the given widget types only (1 API call per type).
     *
     * @param  string[]         $types Widget type IDs present on the page.
     * @return WidgetTemplate[] Templates indexed by type.
     */
    public function getWidgetTemplatesForTypes(array $types): array {
        $templates = [];
        foreach (array_unique($types) as $type) {
            $template = $this->getResourceElement(
                WidgetTemplate::class,
                $this->replaceWildcards(Resource::GET_WIDGET_TEMPLATE_BY_ID, ['id' => $type])
            );
            if ($template instanceof WidgetTemplate) {
                $templates[$type] = $template;
            }
        }
        return $templates;
    }

    /**
     * SDK `Service::getElements()` types `$parameters` strictly as `?ParametersGroup`,
     * but our endpoints need raw key/value arrays (language, pageType). This thin
     * wrapper calls `getResponse()` directly so array-based url params work.
     *
     * @param array<string, string|int|bool> $urlParams
     */
    private function fetchCollection(string $class, string $resource, array $urlParams = []): ?ElementCollection {
        return $this->getResponse(
            $this->call(
                (new RequestBuilder())->path($resource)->urlParams($urlParams)->build()
            ),
            $class
        );
    }

    /**
     * Overrides SDK `Service::call()` to:
     *   1) inject the Bearer JWT header dcsapi expects,
     *   2) route static widget-template GETs through Redis (page-data fetches
     *      mutate on every editor save and stay uncached; X-DEVEL-HEADER and
     *      canvas-mode requests both bypass cache so changes show up immediately),
     *   3) wrap bare-array list responses into `{items: [...]}` so
     *      `getResponse()` / `getElements()` work on our endpoints.
     */
    protected function call(Request $request, string $apiUrl = null): array {
        $token = MagicfrontToken::getToken();
        if (!empty($token)) {
            $request->setHeader('Authorization', 'Bearer ' . $token);
        }

        $apiUrl    = $apiUrl ?? $this->getApiUrl();
        $devel     = defined('DEVEL_HEADER') && DEVEL_HEADER;
        $cacheable = !$devel
            && !$this->bypassCache
            && !MagicfrontUtils::isCanvasMode()
            && $request->getMethod() === 'GET'
            && $this->isCacheablePath($request->getPath());

        $response = $cacheable
            ? $this->cacheSuccessOnly($request, $apiUrl)
            : parent::call($request, $apiUrl);

        unset($response['httpStatus']);
        if (!empty($response) && array_is_list($response)) {
            $response = ['items' => $response];
        }
        return $response;
    }

    private function getApiUrl(): string {
        return Environment::get('MF_API_URL');
    }

    private function isCacheablePath(string $path): bool {
        foreach (self::CACHEABLE_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
