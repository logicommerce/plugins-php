<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Services;

use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontToken;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontUtils;
use Plugins\ComLogicommerceMagicfront\Core\Services\SuccessCacheTrait;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageFacts;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Enums\Resource;
use Plugins\ComLogicommerceMagicfront\Enums\SampleSituationParam;
use SDK\Core\Builders\RequestBuilder;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\Request;
use SDK\Core\Resources\Environment;
use SDK\Core\Resources\Server;
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
        // Sample content is shipped with the backend and only changes on deploy — static like a template.
        Resource::SAMPLES_BASE,
    ];

    private const PARALLEL_READ_FUNCTIONS = [
        'curl_multi_init',
        'curl_multi_add_handle',
        'curl_multi_exec',
        'curl_multi_select',
        'curl_multi_getcontent',
        'curl_multi_remove_handle',
        'curl_multi_close'
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
        // A node of a Studio document (`store:<slug>@N`) is not in any page: its id is a nodeKey, unique only
        // inside that store widget, so the read names the document. Page widgets keep their bare-id read.
        $params = MagicfrontUtils::isStudioDocumentRef($pageId)
            ? ['language' => $language, 'page' => $pageId]
            : ['language' => $language];
        $widget = $this->getResourceElement(
            WidgetInstance::class,
            $this->replaceWildcards(Resource::GET_PAGE_WIDGET_BY_ID, ['pageId' => $pageId, 'widgetId' => $widgetId]),
            $params
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
     * What the editor path needs from the page record in ONE `GET /pages/{pageId}`: the `page.chrome`
     * doc-id refs `{header:<id>, footer:<id>}` (empty kinds omitted so the caller falls back to the
     * commerce default per kind via {@see getChromeDoc()} with `$asDefault`) and the page type (`HOME`,
     * `PRODUCT`, …) — the canvas loads any page from the shop root, so the type is the only way to know
     * a PRODUCT page is being painted.
     *
     * A Studio document (`store:`/`draft:`) also says its placement (BLOCK | SECTION), so the Studio layout can give a
     * BLOCK widget the side room a page section gives it.
     *
     * @return PageFacts
     */
    public function getPageFacts(string $pageId): PageFacts {
        return new PageFacts($this->call(
            (new RequestBuilder())
                ->path($this->replaceWildcards(Resource::GET_PAGE_BY_ID, ['pageId' => $pageId]))
                ->build()
        ));
    }

    /**
     * MagicFront's sample content of a kind (`GET /samples/{kind}?language=`), as the raw array the
     * backend returns: for `product`, `{kind, product: <SDK Product shape>, customTags: {pId: {name, controlType}}}`.
     * A piece the response lacks is painted empty by the caller.
     * `$situation` is the editor simulator's `{stock, offer}` ({@see SampleSituationParam::fromRequest}).
     *
     * @param array $situation
     * @return array
     */
    public function getSample(string $kind, string $language, array $situation = []): array {
        return $this->call(
            (new RequestBuilder())
                ->path($this->replaceWildcards(Resource::GET_SAMPLE, ['kind' => $kind]))
                ->urlParams(['language' => $language] + $situation)
                ->build()
        );
    }

    /**
     * Returns the Magic Front page ID for the given route ID (0 = home page).
     */
    /*public function getPageId(string $routeId): string {
        return $this->getPageIdByType((int) $routeId === 0 ? 'HOME' : 'LANDING');
    }*/

    /**
     * Resolves the singleton page id for a dcsapi pageType (HOME, LANDING,
     * BLOG_CATEGORY, BLOG_POST, ...). Empty string when no page of that type exists.
     */
    /*public function getPageIdByType(string $pageType): string {
        // Raw call (no DTO hydration): the plugin Page inherits SDK's `int $id`,
        // but Java pages use string UUIDs, so hydrating as Page would TypeError.
        $data = $this->call(
            (new RequestBuilder())
                ->path(Resource::GET_PAGES)
                ->urlParams(['pageType' => $pageType])
                ->build()
        );
        return (string) ($data['items'][0]['id'] ?? '');
    }*/

    /**
     * Returns templates for the given widget types only (1 API call per type).
     *
     * Uncached reads (the editor canvas, X-DEVEL-HEADER, a service with the cache disabled) are sent all at once:
     * one by one, a checkout page's 54 templates took 9.5 s from the store to dcsapi, each round trip waiting for
     * the previous one. A type whose parallel read fails is read again the usual way, so an error behaves as before.
     * Where the store's PHP disables a curl_multi function, every type is read the usual way.
     *
     * @param  string[]         $types Widget type IDs present on the page.
     * @return WidgetTemplate[] Templates indexed by type.
     */
    public function getWidgetTemplatesForTypes(array $types): array {
        $types = array_values(array_unique($types));
        $paths = [];
        foreach ($types as $type) {
            $paths[$type] = $this->replaceWildcards(Resource::GET_WIDGET_TEMPLATE_BY_ID, ['id' => $type]);
        }
        $parallel = count($paths) > 1 && !$this->cacheableGet(Resource::WIDGET_TEMPLATES_BASE) && $this->parallelReadsAvailable();
        $fetched = $parallel ? $this->getAll($paths) : [];
        $templates = [];
        foreach ($paths as $type => $path) {
            $template = isset($fetched[$type])
                ? $this->prepareElement($fetched[$type], WidgetTemplate::class)
                : $this->getResourceElement(WidgetTemplate::class, $path);
            if ($template instanceof WidgetTemplate) {
                $templates[$type] = $template;
            }
        }
        return $templates;
    }

    /** Whether every function {@see getAll()} calls exists: production PHP can disable some of them (curl_multi_exec). */
    protected function parallelReadsAvailable(): bool {
        return count(array_filter(self::PARALLEL_READ_FUNCTIONS, 'function_exists')) === count(self::PARALLEL_READ_FUNCTIONS);
    }

    /**
     * GETs every path of $paths at the same time, with the headers {@see call()} sends to dcsapi.
     *
     * @param  array<string, string> $paths key => resource path
     * @return array<string, array>  key => decoded body, only for the reads answered 2xx with a JSON object
     */
    private function getAll(array $paths): array {
        $headers = ['Accept: application/json'];
        $token = MagicfrontToken::getToken();
        if (!empty($token)) {
            $headers[] = 'Authorization: Bearer ' . $token;
        }
        $timeout = Server::get('HTTP_API_TIMEOUT') ?? (defined('API_TIMEOUT') ? API_TIMEOUT : null);
        $multi = curl_multi_init();
        $handles = [];
        foreach ($paths as $key => $path) {
            $handle = curl_init($this->getApiUrl() . $path);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => is_null($timeout) ? 10 : intval($timeout),
                CURLOPT_USERAGENT      => 'PHP SDK for LogiCommerce',
            ]);
            curl_multi_add_handle($multi, $handle);
            $handles[$key] = $handle;
        }
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi);
            }
        } while ($running > 0 && $status === CURLM_OK);
        $results = [];
        foreach ($handles as $key => $handle) {
            $code = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $body = curl_multi_getcontent($handle);
            $data = $code >= 200 && $code < 300 && is_string($body) ? json_decode($body, true) : null;
            if (is_array($data) && $data !== [] && !array_is_list($data)) {
                $results[$key] = $data;
            }
            curl_multi_remove_handle($multi, $handle);
        }
        curl_multi_close($multi);
        return $results;
    }

    /**
     * SDK `Service::getElements()` types `$parameters` strictly as `?ParametersGroup`,
     * but our endpoints need raw key/value arrays (language, pageType). This thin
     * wrapper calls `getResponse()` directly so array-based url params work.
     *
     * @param array $urlParams
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
    protected function call(Request $request, ?string $apiUrl = null): array {
        $token = MagicfrontToken::getToken();
        if (!empty($token)) {
            $request->setHeader('Authorization', 'Bearer ' . $token);
        }

        $apiUrl    = $apiUrl ?? $this->getApiUrl();
        $cacheable = $request->getMethod() === 'GET' && $this->cacheableGet($request->getPath());

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

    /** Whether a GET of $path goes through the Redis cache: never in the canvas, under X-DEVEL-HEADER or with the cache disabled. */
    private function cacheableGet(string $path): bool {
        return !(defined('DEVEL_HEADER') && DEVEL_HEADER)
            && !$this->bypassCache
            && !MagicfrontUtils::isIframeRequest()
            && $this->isCacheablePath($path);
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
