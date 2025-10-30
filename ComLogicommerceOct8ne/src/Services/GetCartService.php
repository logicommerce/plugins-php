<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use Plugins\ComLogicommerceOct8ne\Mapper\CartMapper;

/**
 * This is the class GetCartService to get the cart information
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see CartMapper
 */

class GetCartService extends BaseService {

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /* 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        $this->validateLoggedIn();
        return $this->getCart();
    }

    /* 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return false;
    }

    private function getCart(): object {
        $basket = $this->getSession()->getBasket();
        $cartMapper = new CartMapper($basket);
        return $cartMapper->map();
    }
}