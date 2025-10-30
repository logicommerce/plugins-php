<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductDTO;
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

    private array $products = [];

    public function __construct(array $products, ?PluginProperties $pluginProperties = null) {
        $this->products = $products;
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
            $productDTO->setUseProductUrl(true);
            $response[] = $productDTO;
        }
        return $response;
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