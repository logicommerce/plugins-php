<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Core\Resources\Session;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\Utils;
use SDK\Core\Dtos\PluginProperties;
use FWK\Enums\Services;
use FWK\Core\Resources\Loader;
use FWK\Services\PluginService;
use SDK\Services\Parameters\Groups\PluginDataParametersGroup;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;
use Plugins\ComLogicommerceOct8ne\Mapper\ErrorMapper;
use Plugins\ComLogicommerceOct8ne\Dtos\common\ErrorDTO;

/**
 * This is the class BaseService for the services
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see PluginRouteParameters
 * @see PluginProperties
 * @see PluginPropertiesPropertyNames
 * 
 */
abstract class BaseService {

    public const PLUGIN_MODULE = 'com.logicommerce.oct8ne';

    protected array $params = [];

    protected ?PluginProperties $pluginProperties = null;

    private ?PluginService $pluginService = null;

    private const PRODUCT_LIMIT = 100;

    protected function __construct($params, $pluginProperties) {
        $this->params = $params;
        $this->pluginProperties = $pluginProperties;
    }

    abstract function process(): mixed;

    abstract function isCacheable(): bool;

    /**
     * Get the request parameter
     *
     * @param string $parameter
     * @param mixed $default
     *
     * @return mixed
     */
    protected function getRequestParam(string $parameter, $default = null) {
        if (isset($this->getRequestParams()[$parameter])) {
            return $this->getRequestParams()[$parameter];
        } else {
            return $default;
        }
    }

    /**
     * Get the request parameters
     *
     * @return array
     */
    protected function getRequestParams(): array {
        return $this->params;
    }

    /**
     * Get the product limit
     *
     * @param int $limit
     *
     * @return int
     */
    protected function getProductLimit($limit): int {
        if ($limit > self::PRODUCT_LIMIT) {
            return self::PRODUCT_LIMIT;
        }
        return $limit;
    }

    /**
     * Get the plugin properties
     *
     * @return PluginProperties|null
     */
    protected function getPluginProperties(): ?PluginProperties {
        return $this->pluginProperties;
    }

    /**
     * Get the session
     *
     * @return Session|null
     */
    protected function getSession(): ?Session {
        return Session::getInstance();
    }

    /**
     * Validate if the user is logged in
     *
     */
    protected function validateLoggedIn(): void {
        if (!Utils::isSessionLoggedIn($this->getSession())) {
            Response::forbidden();
        }
    }

    /**
     * Validate the token
     *
     * @return bool
     */
    protected function validateToken() {
        $apiToken = $this->getRequestParam(PluginRouteParameters::PARAMETER_API_TOKEN, '');
        $pluginApiToken = $this->getApiToken();
        if ($apiToken === $pluginApiToken) {
            return true;
        }
        return false;
    }

    /**
     * Get the plugin property value
     *
     * @param string $name
     *
     * @return string
     */
    protected function getPluginPropertyValue($name): string {
        foreach ($this->pluginProperties->getProperties() as $property) {
            if ($name == $property->getName()) {
                return $property->getValue();
            }
        }
        return "";
    }

    /**
     * Get the plugin
     *
     * @return object
     */
    protected function getPlugin(): object {
        $this->pluginService = Loader::service(Services::PLUGIN);
        return $this->pluginService->getRoutePluginByModule($this::PLUGIN_MODULE);
    }

    private function getApiToken(): mixed {
        $plugin = $this->getPlugin();
        if (empty($plugin) || !$plugin->isActive()) {
            return "";
        }
        $action = 'getToken';
        $data = new PluginDataParametersGroup();
        $data->setAction($action);

        $results = $this->pluginService->executePluginData(
            $plugin->getId(),
            $plugin->getModule(),
            $data
        );
        return $results->getData()['apiToken'];
    }

    /**
     * Get the error message
     *
     * @param string $parameter
     *
     * @return string
     */
    protected function getErrorMessage($parameter): string {
        $message = "Parameter '%param%' are required";
        return str_replace('%param%', $parameter, $message);
    }

    /**
     * get error mapper
     *
     * @param string $message
     *
     * @return ErrorMapper
     */
    protected function getErrorMapper($message): ErrorDTO {
        $errorMapper = new ErrorMapper($message);
        return $errorMapper->map();
    }

    /**
     * Check if the ids are numbers
     *
     * @param string $ids
     *
     * @return bool
     */
    protected function checkIds($ids): bool {
        $ids = explode(',', $ids);
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                return false;
            }
        }
        return true;
    }
}