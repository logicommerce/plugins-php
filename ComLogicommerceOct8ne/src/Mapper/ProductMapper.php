<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Product\VariationDTO;
use Plugins\ComLogicommerceOct8ne\Services\BaseService;
use FWK\Core\Resources\Session;
use SDK\Core\Dtos\PluginProperties;
use SDK\Dtos\Catalog\Product\Product;

/**
 * This is the class ProductMapper to map the product data
 *
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 *
 * @see BaseMapper
 * @see ProductDTO
 * @see Product
 */
class ProductMapper extends BaseMapper {

    public const INCLUDE_STOCK = 'stock';

    public const INCLUDE_METAFIELDS = 'metafields';

    public const INCLUDE_PRICE = 'price';

    public const INCLUDE_CATEGORIES = 'categories';

    public const INCLUDE_VARIATIONS = 'variations';

    private array $products = [];

    private array $includes = [];

    public function __construct(array $products, ?PluginProperties $pluginProperties = null, array $includes = []) {
        $this->products = $products;
        $this->includes = $includes;
        parent::__construct($pluginProperties);
    }

    /**
     * Map the product data
     *
     * @return array
     */
    public function map(): array {
        $response = [];
        foreach ($this->products as $product) {
            $productDTO = new ProductDTO(
                $product->getId(),
                $product->getLanguage()->getName(),
                $product->getLanguage()->getShortDescription()
            );
            $productDTO->setLongDescription($this->getLongDescription($product));
            $prices = $this->getPrices($product);
            $price = $prices['price'];
            $basePrice = $prices['basePrice'];
            $productDTO->setReference($this->getReference($product));
            $productDTO->setProductUrl($this->formatProductUrl($product->getLanguage()->getUrlSeo()));
            $productDTO->setThumbnail($product->getMainImages()->getSmallImage());
            $productDTO->setFormattedPrice($this->formatCurrency($price));
            $productDTO->setFormattedPrevPrice($this->formatCurrency($basePrice));
            $productDTO->setMedias($this->getMedias($product));
            $productDTO->setUseProductUrl(false);
            $productDTO->setAddToCartUrl($this->buildAddToCartUrl((string) $product->getId()));

            if (in_array(self::INCLUDE_STOCK, $this->includes, true)) {
                $productDTO->setStock($this->getStockData($product));
            }
            if (in_array(self::INCLUDE_METAFIELDS, $this->includes, true)) {
                $productDTO->setMetafields($this->getMetafieldsData($product));
            }
            if (in_array(self::INCLUDE_PRICE, $this->includes, true)) {
                $productDTO->setPrice($this->getPriceData($product, $price, $basePrice));
            }
            if (in_array(self::INCLUDE_CATEGORIES, $this->includes, true)) {
                $productDTO->setCategories($this->getCategoriesData($product));
            }
            if (in_array(self::INCLUDE_VARIATIONS, $this->includes, true)) {
                $productDTO->setVariations($this->getVariationsData($product, $price));
            }

            $response[] = $productDTO;
        }
        return $response;
    }

    /**
     * Builds the "visit this URL to add the product (and, optionally, a specific
     * variation) to the basket" link exposed to the Oct8ne plugin route.
     */
    private function buildAddToCartUrl(string $productId, ?string $combinationId = null): string {
        $url = $this->formatProductUrl(
            '/' . INTERNAL_PREFIX . '/resources/plugin_route/' . BaseService::PLUGIN_MODULE . '/oct8ne/frame/addproduct?productId=' . $productId
        );
        if ($combinationId !== null) {
            $url .= '&combinationId=' . $combinationId;
        }
        return $url . '&qty=1';
    }

    private function getStockData(Product $product): array {
        return [
            'quantity' => $product->getTotalStock()
        ];
    }

    private function getMetafieldsData(Product $product): array {
        $metafields = [];
        foreach ($product->getCustomTagValues() as $customTag) {
            $metafields[$customTag->getName()] = $customTag->getValue();
        }
        return $metafields;
    }

    private function getPriceData(Product $product, float $price, float $basePrice): array {
        return [
            'price' => $price,
            'originalPrice' => $basePrice,
            'currency' => Session::getInstance()->getGeneralSettings()->getCurrency()
        ];
    }

    private function getCategoriesData(Product $product): array {
        $categories = [];
        foreach ($product->getCategories() as $category) {
            $categories[] = $category->getName();
        }
        return $categories;
    }

    /**
     * Builds one VariationDTO per product combination.
     * Note: the absolute price of a variation is not stored directly on the
     * combination in the SDK - it is derived as (base product price) + (sum of
     * the price deltas of its selected option values), the same approach the
     * product grid view uses (see FWK\ViewHelpers\Product\ProductGridJsonData).
     *
     * @return VariationDTO[]
     */
    private function getVariationsData(Product $product, float $basePrice): array {
        $selectedOptionValueIds = $this->getSelectedOptionValueIds($product);
        $variations = [];

        foreach ($product->getCombinations() as $combination) {
            $optionValueIds = array_map(
                fn($combinationValue) => $combinationValue->getProductOptionValueId(),
                $combination->getValues()
            );

            $options = [];
            $priceDelta = 0.0;
            $thumbnail = '';
            foreach ($product->getOptions() as $option) {
                foreach ($option->getValues() as $optionValue) {
                    if (!in_array($optionValue->getId(), $optionValueIds, true)) {
                        continue;
                    }
                    $options[$option->getLanguage()->getName()] = $optionValue->getLanguage()->getValue();
                    $priceDelta += $optionValue->getPrices()?->getRetailPrice() ?? 0.0;
                    if ($thumbnail === '' && $optionValue->getImages() !== null) {
                        $thumbnail = $optionValue->getImages()->getSmallImage();
                    }
                }
            }

            $stockUnits = 0;
            foreach ($combination->getStocks() as $stock) {
                $stockUnits += $stock->getUnits();
            }

            $sortedOptionValueIds = $optionValueIds;
            sort($sortedOptionValueIds);
            $sortedSelectedIds = $selectedOptionValueIds;
            sort($sortedSelectedIds);

            $variationDTO = new VariationDTO((string) $combination->getId());
            $variationDTO->setAddToCartUrl($this->buildAddToCartUrl((string) $product->getId(), (string) $combination->getId()));
            $variationDTO->setStock(['quantity' => $stockUnits]);
            $variationDTO->setOptions($options);
            $variationDTO->setDefault(!empty($sortedSelectedIds) && $sortedOptionValueIds === $sortedSelectedIds);
            if ($thumbnail !== '') {
                $variationDTO->setThumbnail($thumbnail);
            }
            if ($priceDelta !== 0.0) {
                $variationPrice = $basePrice + $priceDelta;
                $variationDTO->setFormattedPrice($this->formatCurrency($variationPrice));
                $variationDTO->setFormattedPrevPrice($this->formatCurrency($variationPrice));
            }

            $variations[] = $variationDTO;
        }

        return $variations;
    }

    /**
     * Returns the option value ids currently resolved as "selected" for this
     * product (i.e. the combination that getCombinationData() resolved to),
     * used to flag the matching variation as the default one.
     */
    private function getSelectedOptionValueIds(Product $product): array {
        $ids = [];
        $combinationData = $product->getCombinationData();
        if ($combinationData === null) {
            return $ids;
        }
        foreach ($combinationData->getOptions() as $option) {
            foreach ($option->getValues() as $value) {
                if ($value->getSelected()) {
                    $ids[] = $value->getId();
                }
            }
        }
        return $ids;
    }

    private function getMedias(Product $product): array {
        $medias = [];
        $medias[] = $product->getMainImages()->getLargeImage();
        foreach ($product->getAdditionalImages() as $media) {
            $medias[] = $media->getLargeImage();
        }
        return $medias;
    }

    private function getLongDescription(Product $product): ?string {
        $longDescription = $product->getLanguage()->getShortDescription();
        if ($product->getLanguage()->getLongDescription() !== null) {
            $longDescription = $product->getLanguage()->getLongDescription();
        }
        $productCustomTagValues = $product->getCustomTagValues();
        if ($productCustomTagValues !== null && count($productCustomTagValues) > 0) {
            $longDescription .= PHP_EOL . '## Extra info:' . PHP_EOL;
            foreach ($productCustomTagValues as $customTag) {
                $longDescription .= $customTag->getName() . ': ' . $customTag->getValue() . PHP_EOL;
            }
        }
        return $longDescription;
    }
}