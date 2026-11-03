<?php

namespace Plugins\ComLogicommerceOct8ne\Services;

use FWK\Enums\Services;
use FWK\Services\ProductService;
use FWK\Core\Resources\Loader;
use FWK\Core\Controllers\Traits\BuidlProductOptionParametersGroupTrait;
use FWK\Services\BasketService;
use Plugins\ComLogicommerceOct8ne\Enums\PluginRouteParameters;
use SDK\Dtos\Catalog\Product\Product;
use SDK\Services\Parameters\Groups\Basket\AddProductParametersGroup;

/**
 * This is the class AddProductService for add product to basket
 *
 * @package Plugins\ComLogicommerceOct8ne\Services
 *
 * @see BaseService
 * @see ProductMapper
 * @see ErrorMapper
 * @see PluginRouteParameters
 * @see ProductService
 */
class AddProductService extends BaseService {

    use BuidlProductOptionParametersGroupTrait;

    protected ?ProductService $productService = null;

    private ?BasketService $basketService = null;

    protected ?AddProductParametersGroup $addProductParameters = null;

    public function __construct($params, $pluginProperties) {
        parent::__construct($params, $pluginProperties);
    }

    /**
     * Process the request
     *
     * @return array
     */
    public function process(): mixed {
        $productId = $this->getRequestParam(PluginRouteParameters::PARAMETER_PRODUCT_ID, '');
        if (empty($productId)) {
            $message = $this->getErrorMessage(PluginRouteParameters::PARAMETER_PRODUCT_ID);
            return $this->getErrorMapper($message);
        }
        if (!$this->checkIds($productId)) {
            $message = "productId must be a number";
            return $this->getErrorMapper($message);
        }
        $combinationId = $this->getRequestParam(PluginRouteParameters::PARAMETER_COMBINATION_ID, '');
        if (!empty($combinationId) && !$this->checkIds($combinationId)) {
            $message = "combinationId must be a number";
            return $this->getErrorMapper($message);
        }
        $quantity = (int) $this->getRequestParam(PluginRouteParameters::PARAMETER_QUANTITY, 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $options = [];
        if (!empty($combinationId)) {
            $this->productService = Loader::service(Services::PRODUCT);
            $product = $this->productService->getProductById((int) $productId);
            if ($product === null) {
                $message = "productId not found";
                return $this->getErrorMapper($message);
            }
            $options = $this->getOptionsForCombination($product, (int) $combinationId);
            if ($options === null) {
                $message = "combinationId not found for this product";
                return $this->getErrorMapper($message);
            }
        }

        $itemOptionsParameters = [];
        $itemAppliedOptions = [];
        $this->parseOptions($options, $itemOptionsParameters, $itemAppliedOptions);

        $this->basketService = Loader::service(Services::BASKET);
        $this->addProductParameters = new AddProductParametersGroup();
        $this->basketService->generateParametersGroupFromArray($this->addProductParameters, ['id' => $productId, 'quantity' => $quantity, 'options' => $itemOptionsParameters]);
        $this->basketService->addProduct($this->addProductParameters);
        return ['status'=>'success'];
    }

    /**
     * Resolves the given combination id into the "options" shape expected by
     * the basket's AddProductParametersGroup, by matching each of the
     * combination's option value ids against the product's own options.
     *
     * @param Product $product
     * @param int $combinationId
     *
     * @return array|null null when the combination id does not belong to this product
     */
    private function getOptionsForCombination(Product $product, int $combinationId): ?array {
        $combination = null;
        foreach ($product->getCombinations() as $productCombination) {
            if ($productCombination->getId() === $combinationId) {
                $combination = $productCombination;
                break;
            }
        }
        if ($combination === null) {
            return null;
        }

        $options = [];
        foreach ($combination->getValues() as $combinationValue) {
            $optionValueId = $combinationValue->getProductOptionValueId();
            foreach ($product->getOptions() as $option) {
                foreach ($option->getValues() as $optionValue) {
                    if ($optionValue->getId() === $optionValueId) {
                        $options[] = ['id' => (string) $option->getId(), 'values' => [['value' => (string) $optionValueId]]];
                        break 2;
                    }
                }
            }
        }
        return $options;
    }

    /**
     * Check if the request is cacheable
     *
     * @return bool
     */
    public function isCacheable(): bool {
        return false;
    }
}