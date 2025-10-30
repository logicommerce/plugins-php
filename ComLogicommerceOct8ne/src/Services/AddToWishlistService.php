<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Core\Resources\Loader;
use FWK\Services\UserService;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;

/**
 * This is the class AddToWishlistService to add a product to the wishlist
 * 
 * @package Plugins\ComLogicommerceOct8ne\Services
 * 
 * @see BaseService
 * @see PluginRouteParameters
 * @see UserService
 */
class AddToWishlistService extends BaseService {

    protected ?UserService $userService = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /** 
     * Process the request
     * 
     * @return mixed
     */
    public function process(): mixed {
        $productIds = $this->getRequestParam(PluginRouteParameters::PARAMETER_PRODUCT_IDS, '');
        if (empty($productIds)) {
            $message = $this->getErrorMessage(PluginRouteParameters::PARAMETER_PRODUCT_IDS);
            return $this->getErrorMapper($message);
        }
        if (!$this->checkIds($productIds)) {
            $message = "productIds must be a list of number";
            return $this->getErrorMapper($message);
        }
        return $this->addProductToShoppingList($productIds);
    }

    /** 
     * Check if the request is cacheable
     * 
     * @return bool
     */
    public function isCacheable(): bool {
        return false;
    }

    private function addProductToShoppingList($productIds): array {
        $this->userService = Loader::service(Services::USER);
        $response = ['result' => 'true'];
        try {
            $arrProductIds = explode(',', $productIds);
            foreach ($arrProductIds as $productId) {
                $this->userService->addProductToDefaultShoppingListRows($productId);
            }
        } catch (\Error | \Exception $e) {
            $response = ['result' => 'false'];
        }
        return $response;
    }

}