<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Core\Resources\Utils;
use FWK\Enums\Services;
use FWK\Core\Resources\Loader;
use FWK\Services\UserService;
use Plugins\ComLogicommerceOct8ne\Mapper\UserMapper;
use Plugins\ComLogicommerceOct8ne\Dtos\User\UserDTO;

/**
 * This is the class CustomerDataService to get the customer data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see UserMapper
 * @see UserService
 */
class CustomerDataService extends BaseService {

    private ?UserService $userService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /**
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        return $this->getCustomerData();
    }

    /**
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return false;
    }

    private function getCustomerData(): object {
        if (!Utils::isSessionLoggedIn($this->getSession())) {
            return new UserDTO(null);
        }
        $user = $this->getSession()->getUser();
        $basket = $this->getSession()->getBasket();
        $products = $this->getShippingListProducts();
        $userMapper = new UserMapper($user, $basket, $products, $this->pluginProperties);
        return $userMapper->map();
    }

    private function getShippingListProducts(): ?array {
        $this->userService = Loader::service(Services::USER);
        $shoppingList = $this->userService->getDefaultShoppingListRows();
        return $shoppingList->getProducts();
    }
}