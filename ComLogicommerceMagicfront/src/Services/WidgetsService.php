<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Services;

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
 * @see WidgetsService::setToken()
 * @see WidgetsService::getPageWidgets()
 * @see WidgetsService::getPageWidgetInstances()
 * @see WidgetsService::getPageWidgetById()
 * @see WidgetsService::getPageId()
 * @see WidgetsService::getToken()
 * @see WidgetsService::getWidgetTemplatesForTypes()
 *
 * @package Plugins\ComLogicommerceMagicfront\Services
 */
class WidgetsService extends Service {

    private static ?self $instance = null;

    private ?string $token = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sets the JWT Bearer token used to authenticate subsequent calls against the
     * Java dcsapi. Returns `$this` so callers can chain with the fetch method.
     */
    public function setToken(string $token): self {
        $this->token = $token;
        return $this;
    }

    /**
     * Returns all widgets for the given page, transformed into Page format.
     *
     * `$language` is required: the Java endpoint applies locale filtering only when
     * `?language=` is present. Calling without it returns the multi-locale shape
     * `[{language, value}, ...]` which would render as JSON-literal text in Twig.
     * All current callers (storefront route, customize handlers) already have a
     * locale in scope; making the parameter mandatory closes the door so a future
     * caller can't silently break the renderer.
     */
    public function getPageWidgets(string $pageId, string $language): ?ElementCollection {
        return WidgetToPageTransformer::transform(
            $this->fetchCollection(
                WidgetInstance::class,
                $this->replaceWildcards(Resource::GET_PAGE_WIDGETS, ['pageId' => $pageId]),
                ['language' => $language]
            )
        );
    }

    /**
     * Returns raw WidgetInstance objects for the given page. See {@see getPageWidgets()}
     * for why `$language` is required.
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
     * Returns a single widget by ID, transformed into Page format.
     */
    public function getPageWidgetById(string $pageId, string $widgetId, string $language): ?Page {
        $widget = $this->getResourceElement(
            WidgetInstance::class,
            $this->replaceWildcards(Resource::GET_PAGE_WIDGET_BY_ID, ['pageId' => $pageId, 'widgetId' => $widgetId]),
            ['language' => $language]
        );
        return $widget instanceof WidgetInstance
            ? WidgetToPageTransformer::transformSingle($widget)
            : null;
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
     * Exchanges a BOB token for a Magic Front JWT token. Bypasses the `call()`
     * override because the BOB token is not the JWT stored on `$this->token`.
     */
    public function getToken(string $bobToken): string {
        $data = parent::call(
            (new RequestBuilder())
                ->path(Resource::AUTH)
                ->headers(['Authorization' => 'Bearer ' . $bobToken])
                ->build(),
            $this->getApiUrl()
        );
        return (string) $data['token'];
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
     * Overrides SDK `Service::call()` to (1) inject the Bearer JWT header that
     * the Java dcsapi expects — SDK's Connection only knows LogiCommerce-native
     * auth — and (2) wrap bare-array list responses into `{items: [...]}` so
     * `getResponse()` / `getElements()` work natively on our endpoints.
     */
    protected function call(Request $request, string $apiUrl = null): array {
        if ($this->token !== null) {
            $request->setHeader('Authorization', 'Bearer ' . $this->token);
        }
        $response = parent::call($request, $apiUrl ?? $this->getApiUrl());
        // SDK's Connection::doRequest() appends an `httpStatus` metadata key on
        // every success response. Strip it so the list-shape detection works for
        // endpoints that return a bare JSON array (e.g. /pages/{id}/widgets).
        unset($response['httpStatus']);
        if (!empty($response) && array_is_list($response)) {
            $response = ['items' => $response];
        }
        return $response;
    }

    private function getApiUrl(): string {
        return Environment::get('MGF_API_URL');
    }
}
