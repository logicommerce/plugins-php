<?php

namespace Plugins\ComLogicommerceOmniwallet\Services;

use FWK\Core\Resources\Session;
use FWK\Core\Resources\Response;
use FWK\Core\Resources\Utils;
use SDK\Core\Dtos\PluginProperties;
use FWK\Enums\Services;
use FWK\Core\Resources\Loader;
use FWK\Services\PluginService;
use SDK\Services\Parameters\Groups\PluginDataParametersGroup;
use Plugins\ComLogicommerceOmniwallet\Mapper\ErrorMapper;
use Plugins\ComLogicommerceOmniwallet\Dtos\common\ErrorDTO;

/**
 * This is the class BaseService for the services
 * 
 * @package Plugins\ComLogicommerceOmniwallet\Services
 * 
 * @see PluginRouteParameters
 * @see PluginProperties
 * @see PluginPropertiesPropertyNames
 * 
 */
abstract class BaseService {

    public const PLUGIN_MODULE = 'com.logicommerce.omniwallet';

    protected array $params = [];

    protected ?PluginProperties $pluginProperties = null;

    private ?PluginService $pluginService = null;

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
     * Get the plugin
     *
     * @return object
     */
    protected function getPlugin(): object {
        $this->pluginService = Loader::service(Services::PLUGIN);
        return $this->pluginService->getRoutePluginByModule($this::PLUGIN_MODULE);
    }

    protected function getApiToken($email): mixed {
        $plugin = $this->getPlugin();
        if (empty($plugin) || !$plugin->isActive()) {
            return "";
        }
        $action = 'getToken';
        $data = new PluginDataParametersGroup();
        $data->setAction($action);
        $data->setData([ 'email' => $email ]);

        $results = $this->pluginService->executePluginData(
            $plugin->getId(),
            $plugin->getModule(),
            $data
        );
        return $results->getData();
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

}
