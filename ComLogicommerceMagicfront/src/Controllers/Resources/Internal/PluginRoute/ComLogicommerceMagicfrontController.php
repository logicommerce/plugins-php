<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute;

use FWK\Core\Controllers\BaseJsonController;
use FWK\Core\FilterInput\FilterInput;
use FWK\Core\FilterInput\FilterInputHandler;
use FWK\Core\Resources\Response;
use FWK\Enums\Parameters;
use FWK\Twig\TwigLoader;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\CustomizeCssJsHandler;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Handlers\GetWidgetHandler;
use Plugins\ComLogicommerceMagicfront\Core\Interfaces\PluginRouteHandlerInterface;
use SDK\Core\Dtos\Element;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin-route dispatcher for Magicfront editor AJAX calls. Routes each
 * incoming request to a handler that matches the `type` parameter:
 *  - `customizeCssJs` → CSS + JS bundle for the current page's widgets.
 *  - `getWidget`      → HTML/CSS/JS of a single widget (live preview).
 *
 * Handlers implement PluginRouteHandlerInterface. The controller picks the
 * first handler whose `supports()` returns true. If the handler declares a
 * raw response, its content-type and body are streamed directly; otherwise
 * the controller falls back to BaseJsonController's DTO flow.
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Resources\Internal\PluginRoute
 */
class ComLogicommerceMagicfrontController extends BaseJsonController {

    public const PLUGIN_MODULE = 'com.logicommerce.magicfront';

    /** @var PluginRouteHandlerInterface[] */
    private array $handlers = [];

    private ?PluginRouteHandlerInterface $handler = null;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->handlers = [
            new CustomizeCssJsHandler(),
            new GetWidgetHandler(),
        ];
    }

    /**
     * Read request params from the GET query string.
     *
     * @see FilterInputHandler
     */
    protected function getOriginParams(): int {
        return FilterInputHandler::PARAMS_FROM_GET;
    }

    /**
     * Defines expected input parameters.
     */
    protected function getFilterParams(): array {
        $noMod = [FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false];
        return [
            Parameters::WIDGET_ID => new FilterInput($noMod),
            Parameters::PAGE      => new FilterInput($noMod),
            Parameters::TYPE      => new FilterInput($noMod),
            Parameters::LANGUAGE  => new FilterInput($noMod),
        ];
    }

    protected function initializeAppliedParameters(): void {
        parent::initializeAppliedParameters();
        $type          = (string) ($this->getRequestParam(Parameters::TYPE, false) ?? '');
        $this->handler = $this->resolveHandler($type);
    }

    public function run(array $additionalData = [], string $header = null): void {
        // The parent flow does not guarantee initializeAppliedParameters() has
        // been called before run(), so make sure the handler is resolved first.
        if ($this->handler === null) {
            $this->initializeAppliedParameters();
        }

        try {
            if ($this->handler !== null && $this->handler->isRawResponse()) {
                $this->streamRawResponse($this->handler);
                return;
            }
            parent::run($additionalData, $header);
        } catch (\Throwable $e) {
            $this->streamErrorResponse($e);
        }
    }

    protected function getResponseData(): ?Element {
        return $this->handler?->handle($this);
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

    private function streamRawResponse(PluginRouteHandlerInterface $handler): void {
        $contentType = $handler->getRawResponseContentType();
        $isJs        = $contentType !== null && str_contains($contentType, 'javascript');

        Response::setType($isJs ? Response::TYPE_JS : Response::TYPE_CSS);

        if ($contentType !== null) {
            Response::addHeader('Content-Type: ' . $contentType);
        }

        Response::output($handler->getRawResponseContent($this) ?? '');
    }

    private function streamErrorResponse(\Throwable $e): void {
        $contentType = $this->handler?->getRawResponseContentType() ?? 'text/plain';
        Response::addHeader('Content-Type: ' . $contentType);

        if (str_contains($contentType, 'javascript')) {
            Response::output('// ERROR: ' . str_replace(['//', '/*', '*/'], '', $e->getMessage()));
        } else {
            Response::output('/* ERROR: ' . str_replace(['*/', '/*'], '', $e->getMessage()) . ' */');
        }
    }

    /**
     * @see BaseJsonController::setBatchData()
     */
    protected function setBatchData(BatchRequests $request): void {
    }

    /**
     * @see BaseJsonController::setControllerBaseBatchData()
     */
    final protected function setControllerBaseBatchData(BatchRequests $requests): void {
    }
}
