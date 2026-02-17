<?php

namespace Plugins\ComLogicommerceMagicfront\Services;

use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Enums\Resource;
use SDK\Core\Builders\RequestBuilder;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\Environment;
use SDK\Core\Services\Service;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetInstance;

class WidgetsService extends Service {

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get widgets for a page from DCS API and transform to Page format.
     *
     * @param string $dcsPageId The DCS page ID (e.g., "p-home-123")
     * @param string $language The language code
     * @param string $dcsToken The DCS JWT token for authentication
     * @return ElementCollection|null Collection of Page objects, or null on error
     */
    public function getPageWidgets(string $dcsPageId, string $language, string $dcsToken): ?ElementCollection {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = $this->replaceWildcards(Resource::GET_PAGE_WIDGETS, ['pageId' => $dcsPageId]);

        try {
            // Fetch widgets from DCS API with Authorization header
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->urlParams(['language' => $language])
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->build(),
                $apiUrl
            );
            // Check for error response
            if (is_array($response) && isset($response['error'])) {
                return null;
            }

            // API returns array directly [...], not {"items": [...]}
            $data = is_array($response) ? $response : json_decode($response, true);

            if (empty($data) || !is_array($data) || isset($data['error'])) {
                return null;
            }

            // Create WidgetInstance objects from array
            // Filter out httpStatus and other non-widget entries
            $widgetItems = [];
            foreach ($data as $key => $widgetData) {
                // Skip non-numeric keys (like 'httpStatus') and non-array values
                if (!is_numeric($key) || !is_array($widgetData) || !isset($widgetData['type'])) {
                    continue;
                }
                $widgetItems[] = new WidgetInstance($widgetData);
            }

            // Create ElementCollection manually
            $widgets = new ElementCollection(['items' => $widgetItems]);

            // Transform WidgetInstance -> Page format for PageRelationResolver
            return WidgetToPageTransformer::transform($widgets);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get widget instances for a page from DCS API (raw instances).
     *
     * @param string $dcsPageId The DCS page ID (e.g., "p-home-123")
     * @param string $language The language code
     * @param string $dcsToken The DCS JWT token for authentication
     * @return array Array of WidgetInstance objects
     */
    public function getPageWidgetInstances(string $dcsPageId, string $language, string $dcsToken): array {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = $this->replaceWildcards(Resource::GET_PAGE_WIDGETS, ['pageId' => $dcsPageId]);

        try {
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->urlParams(['language' => $language])
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->build(),
                $apiUrl
            );

            if (is_array($response) && isset($response['error'])) {
                return [];
            }

            $data = is_array($response) ? $response : json_decode($response, true);

            if (empty($data) || !is_array($data) || isset($data['error'])) {
                return [];
            }

            $widgetItems = [];
            foreach ($data as $key => $widgetData) {
                if (!is_numeric($key) || !is_array($widgetData) || !isset($widgetData['type'])) {
                    continue;
                }
                $widgetItems[] = new WidgetInstance($widgetData);
            }

            return $widgetItems;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getPageWidgetById(string $dcsPageId, string $dcswidgetId, string $dcsToken, string $language = 'es'): ?Page {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = $this->replaceWildcards(Resource::GET_PAGE_WIDGET_BY_ID, ['pageId' => $dcsPageId, 'widgetId' => $dcswidgetId]);

        try {
            // Fetch widgets from DCS API with Authorization header
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->urlParams(['language' => $language])
                    ->build(),
                $apiUrl
            );
            // Check for error response
            if (is_array($response) && isset($response['error'])) {
                return null;
            }
            return WidgetToPageTransformer::transformSingle($response);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPageId(string $routeId, string $dcsToken): string {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = Resource::GET_PAGES;

        try {
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->urlParams(['pageType' => $routeId == 0 ? 'HOME' : 'LANDING'])
                    ->build(),
                $apiUrl
            );

            $data = is_array($response) ? $response : json_decode($response, true);
            if (!is_array($data) || isset($data['error'])) {
                return '';
            }

            $pageId = $data['pages'][0]['id'] ?? '';
            return is_string($pageId) ? $pageId : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function getDcsToken(string $bobToken): string {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = Resource::AUTH;
        try {
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->headers(['Authorization' => 'Bearer ' . $bobToken])
                    ->build(),
                $apiUrl
            );

            $data = is_array($response) ? $response : json_decode($response, true);

            if (!is_array($data) || isset($data['error'])) {
                return '';
            }

            $token = $data['token'] ?? '';
            return is_string($token) ? $token : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get widget templates from DCS API
     *
     * @param string $dcsToken DCS JWT token
     * @param string|null $fields Comma-separated API field names to include, or 'all' (default)
     *                            Example: 'templateCss,templateJs' returns only CSS and JS
     *                            Example: 'templateHtml,properties,applicableStyles'
     * @return array Array of templates indexed by type
     */
    public function getWidgetTemplates(string $dcsToken, ?string $fields = null): array {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = Resource::GET_WIDGET_TEMPLATES;

        try {
            // Fetch templates from DCS API
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->build(),
                $apiUrl
            );

            // Decode response if needed
            $data = is_array($response) ? $response : json_decode($response, true);

            if (empty($data) || !is_array($data) || isset($data['error'])) {
                // API failed, return empty array
                return [];
            }

            // Check if data is in 'definitions' key
            if (isset($data['definitions']) && is_array($data['definitions'])) {
                $data = $data['definitions'];
            }

            // Parse requested fields
            $requestedFields = ($fields === null || $fields === 'all')
                ? null  // null means return all fields
                : array_map('trim', explode(',', $fields));

            // Build templates array from API response
            $templates = [];
            foreach ($data as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $type = $item['type'] ?? '';

                if ($type !== '') {
                    // If no specific fields requested, return complete item
                    if ($requestedFields === null) {
                        // Transform HTML if it exists
                        if (isset($item['templateHtml']) && is_string($item['templateHtml'])) {
                            $item['templateHtml'] = WidgetTemplateTransformer::transformAll($item['templateHtml']);
                        } elseif (isset($item['templateHTML']) && is_string($item['templateHTML'])) {
                            $item['templateHTML'] = WidgetTemplateTransformer::transformAll($item['templateHTML']);
                        }
                        $templates[$type] = $item;
                    } else {
                        // Filter to only requested fields (always include type)
                        $template = ['type' => $type];

                        foreach ($requestedFields as $fieldName) {
                            if (isset($item[$fieldName])) {
                                // Special handling for HTML fields (needs transformation)
                                if (($fieldName === 'templateHtml' || $fieldName === 'templateHTML') && is_string($item[$fieldName])) {
                                    $template[$fieldName] = WidgetTemplateTransformer::transformAll($item[$fieldName]);
                                } else {
                                    $template[$fieldName] = $item[$fieldName];
                                }
                            }
                        }

                        $templates[$type] = $template;
                    }
                }
            }

            return $templates;
        } catch (\Exception $e) {
            // On error, return empty array
            return [];
        }
    }

    /**
     * Get widget templates as HTML strings only (for Twig rendering)
     *
     * @param string $dcsToken DCS JWT token
     * @return array Array of HTML strings indexed by type
     */
    public function getWidgetTemplatesAsHtml(string $dcsToken): array {
        $templates = $this->getWidgetTemplates($dcsToken, 'templateHtml');
        $htmlList = [];

        foreach ($templates as $type => $template) {
            if (is_array($template)) {
                // Extract templateHtml or templateHTML field
                $htmlList[$type] = $template['templateHtml'] ?? $template['templateHTML'] ?? '';
            } else {
                // Legacy format: already a string
                $htmlList[$type] = $template;
            }
        }

        return $htmlList;
    }

    /**
     * Get merged CSS from all widget templates
     *
     * @param string $dcsToken DCS JWT token
     * @return string Merged CSS string
     */
    public function getMergedWidgetCss(string $dcsToken, ?array $types = null): string {
        $templates = $this->getWidgetTemplates($dcsToken, 'templateCss');
        if (!empty($types)) {
            $templates = $this->filterTemplatesByTypes($templates, $types);
        }
        $cssArray = [];

        foreach ($templates as $type => $template) {
            if (is_array($template) && isset($template['templateCss'])) {
                $css = $template['templateCss'];
                if (is_string($css) && !empty(trim($css))) {
                    $sanitizedType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
                    $cssArray[] = "/* Widget Template: {$sanitizedType} */";
                    $cssArray[] = trim($css);
                    $cssArray[] = "";
                }
            }
        }

        return implode("\n", $cssArray);
    }

    /**
     * Get merged JS from all widget templates
     *
     * @param string $dcsToken DCS JWT token
     * @return string Merged JS string
     */
    public function getMergedWidgetJs(string $dcsToken, ?array $types = null): string {
        $templates = $this->getWidgetTemplates($dcsToken, 'templateJs');
        if (!empty($types)) {
            $templates = $this->filterTemplatesByTypes($templates, $types);
        }
        $jsArray = [];

        foreach ($templates as $type => $template) {
            if (is_array($template) && isset($template['templateJs'])) {
                $js = $template['templateJs'];
                if (is_string($js) && !empty(trim($js))) {
                    $sanitizedType = preg_replace('/[^a-zA-Z0-9_-]/', '', $type);
                    $jsArray[] = "// Widget Template: {$sanitizedType}";
                    $jsArray[] = "(function() {";
                    $jsArray[] = "    'use strict';";
                    $jsArray[] = "    " . str_replace("\n", "\n    ", trim($js));
                    $jsArray[] = "})();";
                    $jsArray[] = "";
                }
            }
        }

        return implode("\n", $jsArray);
    }

    /**
     * Filter template map by widget types
     */
    private function filterTemplatesByTypes(array $templates, array $types): array {
        if (empty($templates) || empty($types)) {
            return $templates;
        }

        $types = array_values(array_unique(array_filter($types, 'is_string')));
        if (empty($types)) {
            return $templates;
        }

        $lookup = array_fill_keys($types, true);
        return array_intersect_key($templates, $lookup);
    }

    /**
     * Get a single widget template by type
     *
     * @param string $type Widget type (e.g., "heading", "text")
     * @param string $dcsToken The DCS JWT token for authentication
     * @return array|null Widget template data with templateCss field, or null on error
     */
    public function getWidgetTemplateByType(string $type, string $dcsToken): ?array {
        $apiUrl = Environment::get('MGF_API_URL');
        $path = $this->replaceWildcards(Resource::GET_WIDGET_TEMPLATE_BY_TYPE, ['type' => $type]);

        try {
            $response = $this->call(
                (new RequestBuilder())
                    ->path($path)
                    ->headers(['Authorization' => 'Bearer ' . $dcsToken])
                    ->build(),
                $apiUrl
            );

            $data = is_array($response) ? $response : json_decode($response, true);

            if (empty($data) || !is_array($data) || isset($data['error'])) {
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            return null;
        }
    }
}
