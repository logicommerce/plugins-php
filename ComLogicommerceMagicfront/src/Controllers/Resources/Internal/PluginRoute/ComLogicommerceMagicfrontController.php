<?php

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute;

use FWK\Core\Controllers\BaseJsonController;
use FWK\Core\FilterInput\FilterInput;
use FWK\Core\FilterInput\FilterInputHandler;
use FWK\Enums\Parameters;
use FWK\Twig\TwigLoader;
use SDK\Core\Dtos\Element;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;
use FWK\Core\Dtos\ElementCollection as DtosElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use FWK\Enums\ControllerData;
use FWK\Enums\TwigContentTypes;
use FWK\Services\PageService;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\PageRelationResolver;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\WidgetRender;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers\CustomizeCssHandler;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers\CustomizeJsHandler;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers\GetPageInfoHandler;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers\GetWidgetHandler;
use Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\Handlers\PluginRouteHandlerInterface;
use Plugins\ComLogicommerceMagicfront\Services\WidgetsService;
use Plugins\ComLogicommerceMagicfront\Services\WidgetToPageTransformer;

/**
 * Controller to render a single widget's HTML after configuration changes.
 * Used by the DCS Editor for live updates without full page reload.
 */
class ComLogicommerceMagicfrontController extends BaseJsonController {

    public const PLUGIN_MODULE = 'com.logicommerce.magicfront';

    private ?WidgetsService $widgetsService = null;

    private ?PageService $pageService = null;

    private string $getFunctionType = '';

    private array $handlers = [];

    private ?PluginRouteHandlerInterface $handler = null;

    /**
     * Constructor
     */
    public function __construct(Route $route) {
        parent::__construct($route);
        $this->widgetsService = WidgetsService::getInstance();
        $this->pageService = PageService::getInstance();
        $this->handlers = [
            new CustomizeCssHandler(),
            new CustomizeJsHandler(),
            new GetWidgetHandler(),
            new GetPageInfoHandler(),
        ];
    }

    /**
     * This method returns the origin of the params (see FilterInputHandler::PARAMS_FROM_GET, FilterInputHandler::PARAMS_FROM_QUERY_STRING or FilterInputHandler::PARAMS_FROM_POST,...).
     * This function must be override in extended controllers to add new parameters to self::requestParams
     *
     * @return mixed
     *
     * @see FilterInputHandler
     */
    protected function getOriginParams() {
        return FilterInputHandler::PARAMS_FROM_POST_DATA_OBJECT;
    }

    /**
     * Defines expected input parameters
     */
    protected function getFilterParams(): array {
        return [Parameters::WIDGET_ID => new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
        ])] + [Parameters::DCS_PAGE_ID => new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
        ])] + [Parameters::DCS_TOKEN => new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
        ])] + [Parameters::TYPE => new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false
        ])];
    }

    protected function initializeAppliedParameters(): void {
        parent::initializeAppliedParameters();

        // Try to get type parameter (may return null)
        $type = $this->getRequestParam(Parameters::TYPE, false);
        $this->getFunctionType = $type ?? '';

        // If not found, check POST directly
        if (empty($this->getFunctionType) && isset($_POST['type'])) {
            $this->getFunctionType = $_POST['type'];
        }

        // If still empty, check REQUEST
        if (empty($this->getFunctionType) && isset($_REQUEST['type'])) {
            $this->getFunctionType = $_REQUEST['type'];
        }

        $this->handler = $this->resolveHandler($this->getFunctionType);
    }

    public function run(array $additionalData = [], string $header = null): void {
        // IMPORTANT: Initialize parameters BEFORE checking handler
        // Because parent class might not call initializeAppliedParameters before run()
        if (empty($this->getFunctionType)) {
            $this->initializeAppliedParameters();
        }

        try {
            // Check if handler needs to return raw response (like CSS or JavaScript)
            if ($this->handler && $this->handler->isRawResponse()) {
                $contentType = $this->handler->getRawResponseContentType();

                // Set appropriate response type based on content type
                if ($contentType && strpos($contentType, 'javascript') !== false) {
                    Response::setType(Response::TYPE_JS);
                } else {
                    Response::setType(Response::TYPE_CSS);
                }

                if ($contentType) {
                    Response::addHeader('Content-Type: ' . $contentType);
                }

                $content = $this->handler->getRawResponseContent($this);
                Response::output($content ?? '');
                return;
            }
            parent::run($additionalData, $header);
        } catch (\Throwable $e) {
            // Log error
            $logFile = '/home/qinglun/logicommerce/local/phpProject/logs/dcs-error.log';
            $msg = date('Y-m-d H:i:s') . " ERROR: " . $e->getMessage() . "\n";
            $msg .= "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
            $msg .= "Trace: " . $e->getTraceAsString() . "\n\n";
            @file_put_contents($logFile, $msg, FILE_APPEND);

            // Return appropriate error response
            $contentType = $this->handler?->getRawResponseContentType() ?? 'text/plain';
            Response::addHeader('Content-Type: ' . $contentType);

            if (strpos($contentType, 'javascript') !== false) {
                Response::output("// ERROR: " . str_replace(['//', '/*', '*/'], '', $e->getMessage()));
            } else {
                Response::output("/* ERROR: " . str_replace(['*/', '/*'], '', $e->getMessage()) . " */");
            }
        }
    }

    protected function getResponseData(): ?Element {
        if ($this->handler) {
            return $this->handler->handle($this);
        }
        return null;
    }

    public function getRequestParamValue(string $parameter, bool $required = true, mixed $default = null): mixed {
        return $this->getRequestParam($parameter, $required, $default);
    }

    public function addWidgetTwigBaseFunctions(TwigLoader $twig): void {
        $this->addTwigBaseFunctions($twig);
    }

    public function addWidgetTwigBaseExtensions(TwigLoader $twig): void {
        $this->addTwigBaseExtensions($twig);
    }

    public function getDefaultDataForWidgetRender(): array {
        return $this->getDefaultData();
    }

    private function resolveHandler(string $type): ?PluginRouteHandlerInterface {
        foreach ($this->handlers as $handler) {
            if ($handler->supports($type)) {
                return $handler;
            }
        }
        return null;
    }

    /**
     * Process pages recursively to convert them to PluginPage instances
     * @param ElementCollection $pages
     * @return ElementCollection|null
     */
    protected function processPagesRecursive(ElementCollection &$pages): ?ElementCollection {
        $pages = DtosElementCollection::fillFromParentCollection($pages, PluginPage::class);

        foreach ($pages->getItems() as $page) {
            if (!$page instanceof PluginPage) {
                continue;
            }
            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $page->getSubPages()]);
                $this->processPagesRecursive($subPages);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
        return $pages;
    }

    /**
     * This method is the one in charge of defining all the data batch requests that
     * are needed for the controller and adding them to the BatchRequests given by parameter.
     *
     * @param BatchRequests $request
     * @return void
     */
    protected function setBatchData(BatchRequests $request): void {
    }

    /**
     * This method is the one in charge of defining all the data batch requests that are
     * basic for the controller and adding them to the BatchRequests given by parameter.
     *
     * @param BatchRequests $requests
     */
    final protected function setControllerBaseBatchData(BatchRequests $requests): void {
    }
}
