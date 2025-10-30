<?php

namespace Plugins\ComLogicommerceOct8ne\Controllers\Resources\Internal\PluginRoute;

use FWK\Core\Controllers\Controller;
use FWK\Core\FilterInput\FilterInput;
use FWK\Core\FilterInput\FilterInputHandler;
use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\Utils;
use FWK\Core\Theme\Theme;
use FWK\Enums\Parameters;
use FWK\Services\PluginService;
use FWK\Enums\Services;
use FWK\Enums\TwigContentTypes;
use FWK\Twig\TwigLoader;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Enums\Traits\EnumResolverTrait;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRoutePath;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/**
 * This is the Plugin Route controller.
 * This class extends BaseJsonpController (FWK\Core\Controllers\BaseJsonpController), see this class.
 *
 * @see BaseJsonpController
 *
 * @package Plugins\ComLogicommerceOct8ne\Controllers\Resources\Internal
 */
class ComLogicommerceOct8neController extends Controller {
    use EnumResolverTrait;

    public const CALLBACK_FUNCTION = 'callbackFunction';

    public const ENDPOINT_POSITION = 1;

    public const SERVICES_PATH = 'Plugins\ComLogicommerceOct8ne\Services';

    public const PLUGIN_MODULE = 'com.logicommerce.oct8ne';

    public const PLUGIN_PATH_BASE = '/oct8ne/frame/';

    protected ?PluginService $pluginService = null;

    private string $contentType = '';

    private ?object $service = null;

    protected function alterTheme(): void {
        $theme = Theme::getInstance();
        $theme->setName(INTERNAL_THEME);
        $theme->setVersion('');
    }

    /**
     * This method returns an array of the params indicating in each node the param name, and the filter to apply.
     * This function must be override in extended controllers to add new parameters to self::requestParams
     *
     * @return mixed
     */
    protected function getFilterParams(): array {
        $paramsValues = PluginRouteParameters::getValues();
        $filterParams = [];
        foreach ($paramsValues as $param) {
            $filterParams[$param] = new FilterInput([
                FilterInput::CONFIGURATION_NO_FILTER => true
            ]);
        }
        return $filterParams;
    }

    /**
     * This method validate if the session is a company account
     *
     * @return void
     */
    protected function validateCompanyAccounts(): void {
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
        return FilterInputHandler::PARAMS_FROM_GET;
    }

    /**
     * This method is the one in charge of defining all the data batch requests that
     * are needed for the controller and adding them to the BatchRequests given by parameter.
     *
     * @param BatchRequests $request
     *            where the method will add the batch requests.
     * @return void
     */
    protected function setBatchData(BatchRequests $request): void {
    }

    /**
     * This method is the one in charge of defining all the data batch requests that are
     * basic for the controller and adding them to the BatchRequests given by parameter.
     *
     * @param BatchRequests $request
     *            where the method will add the batch requests.
     */
    final protected function setControllerBaseBatchData(BatchRequests $requests): void {
    }

    /**
     * This method is in charge of defining the basic data necessary for the correct operation of the controller.
     * operation of the controller.
     */
    final protected function setControllerBaseData(): void {
        $callback = $this->getRequestParam(PluginRouteParameters::PARAMETER_CALLBACK, '');
        $this->contentType = empty($callback) ? TwigContentTypes::JSON : TwigContentTypes::JSONP;
        $this->setDataValue($this::CALLBACK_FUNCTION, $callback);
        $pathCase = explode($this::PLUGIN_PATH_BASE, $this->getRequestParam(Parameters::PATH));
        $endpoint = $pathCase[$this::ENDPOINT_POSITION];
        $pluginModule = $this::PLUGIN_MODULE;
        $response = [];
        $this->checkRoutePlugin($pluginModule);
        if (isset($endpoint)) {
            $this->service = $this->getServiceObject($endpoint, $pluginModule);
            $response = $this->getResponse();
        }
        $this->setDataValue(self::CONTROLLER_ITEM, $response);
    }

    /**
     * This method runs after the batch requests (defined in the setBatchData methods) are resolved,
     * so here you can work with the response of the batch requests and calculate and set more needed data.
     *
     * @param array $additionalData
     *              Set additiona data to the controller data
     * 
     * @return void
     */
    protected function setData(array $additionalData = []): void {
    }

    /**
     * @see \FWK\Core\Controllers\Controller::setType()
     */
    protected function setType(array $additionalData = [], string $header = null): void {
        Response::setType(($this->contentType == TwigContentTypes::JSON) ? Response::TYPE_JSON : Response::TYPE_JS);
    }

    /**
     *
     * @see \FWK\Core\Controllers\Controller::addTwigBaseFunctions()
     */
    protected function addTwigBaseFunctions(TwigLoader $twig) {
        Loader::twigFunctions(($this->contentType == TwigContentTypes::JSON) ? TwigContentTypes::JSON : TwigContentTypes::JS)->addFunctions($twig->getTwigEnvironment());
    }

    /**
     * @see \FWK\Core\Controllers\Controller::addTwigBaseExtensions()
     */
    protected function addTwigBaseExtensions(TwigLoader $twig) {
        Loader::twigExtensions(($this->contentType == TwigContentTypes::JSON) ? TwigContentTypes::JSON : TwigContentTypes::JS)->addExtensions($twig->getTwigEnvironment());
    }

    /**
     * Override content to 'Content/Json/default.json.twig', and set format to 'json' 
     * 
     * @see \FWK\Core\Controllers\Controller::render()
     */
    protected function render(String $content = null, String $layout = null, String $format = 'json'): string {
        $layout = 'layouts' . '/basic.' . $this->contentType . '.twig';
        $content = 'Content/' . ucfirst($this->contentType) . '/basic.' . $this->contentType . '.twig';
        return parent::render($content, $layout, $this->contentType);
    }

    /**
     * @see \FWK\Core\Controllers\Controller::setTwig()
     */
    protected function setTwig(array $data = [], bool $loadCore = true, int $autoescape = 0): TwigLoader {
        return parent::setTwig([], false, $autoescape);
    }

    /**
     * This method validate if the session is logged in. Else generate a forbidden response
     *
     * @return void
     */
    protected function validateLoggedIn(): void {
        if (!Utils::isSessionLoggedIn($this->getSession())) {
            Response::forbidden();
        }
    }

    /**
     * This method validate if the session is sales agent. Else generate a forbidden response
     *
     * @return void
     */
    protected function validateSalesAgent(): void {
        if (!Utils::isSalesAgent($this->getSession())) {
            Response::forbidden();
        }
    }

    /**
     * This method launch forbidden response if the user is simulated. Else generate a forbidden response
     *
     * @return void
     */
    protected function forbiddenSimulatedUser(): void {
        if (Utils::isSimulatedUser($this->getSession())) {
            Response::forbidden();
        }
    }

    /**
     * Returns if the requests is cacheable
     *
     * @return bool
     */
    protected function isCacheable(): bool {
        if (is_null($this->service)) {
            return false;
        }
        if ($this->service->isCacheable()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * This method add content type header to the response JSON or JSONP
     *
     * @return void
     *
     * @see \FWK\Core\Controllers\Controller::addResponseHeaders()
     */
    protected function addResponseHeaders(): void {
        parent::addResponseHeaders();
        if ($this->contentType == TwigContentTypes::JSONP) {
            Response::addHeader('Content-Type:' . Response::MIME_TYPE_JS . '; charset=' . CHARSET);
        } else {
            Response::addHeader('Content-Type:' . Response::MIME_TYPE_JSON . '; charset=' . CHARSET);
        }
    }

    /**
     * This method returns the service object for the endpoint
     *
     * @return object
     */
    private function getServiceObject($endPoint, $pluginMmodule): ?object {        
        $endPoint = $this->getEnum(PluginRoutePath::class, $endPoint, '');
        $class = self::SERVICES_PATH . '\\' . $endPoint . 'Service';
        if (class_exists($class)) {
            $params = $this->getRequestParams();
            $this->pluginService = Loader::service(Services::PLUGIN);
            $pluginProperties = $this->pluginService->getRoutePluginProperties($pluginMmodule);
            return new $class($params, $pluginProperties);
        }
        return null;
    }

    private function checkRoutePlugin($pluginModule) {
        $this->pluginService = Loader::service(Services::PLUGIN);
        /** @var \SDK\Dtos\Common\Plugin **/
        $plugin = $this->pluginService->getRoutePluginByModule($pluginModule);
        if (empty($plugin) || !$plugin->isActive()) {
            Response::forbidden();
        }
    }

    /**
     * This method returns the response for the endpoint
     *
     * @return mixed
     */
    private function getResponse(): mixed {
        if (is_null($this->service)) {
            Response::forbidden();
        }
        return $this->service->process();
    }
}
