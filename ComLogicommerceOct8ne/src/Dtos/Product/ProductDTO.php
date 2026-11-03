<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

/**
 * This is the product DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 * 
 * @see \JsonSerializable
 */
class ProductDTO extends ProductBaseDTO {

    private string $description;

    private string $longDescription;

    private bool $useProductUrl;

    private string $addToCartUrl;

    private array $medias;

    private ?array $stock = null;

    private ?array $metafields = null;

    private ?array $price = null;

    private ?array $categories = null;

    private ?array $variations = null;

    public function __construct(string $internalId, string $title, string $description) {
        $this->internalId = $internalId;
        $this->title = $title;
        $this->description = $description;
    }

    function setDescription(string $description) {
        $this->description = $description;
    }

    function setUseProductUrl(bool $useProductUrl) {
        $this->useProductUrl = $useProductUrl;
    }

    function getUseProductUrl(): bool {
        return $this->useProductUrl;
    }

    function setAddToCartUrl(string $addToCartUrl) {
        $this->addToCartUrl = $addToCartUrl;
    }

    function getAddToCartUrl(): string {
        return $this->addToCartUrl;
    }

    function setMedias(array $medias) {
        $this->medias = $medias;
    }

    function getMedias(): array {
        return $this->medias;
    }

    function setStock(array $stock) {
        $this->stock = $stock;
    }

    function getStock(): ?array {
        return $this->stock;
    }

    function setMetafields(array $metafields) {
        $this->metafields = $metafields;
    }

    function getMetafields(): ?array {
        return $this->metafields;
    }

    function setPrice(array $price) {
        $this->price = $price;
    }

    function getPrice(): ?array {
        return $this->price;
    }

    function setCategories(array $categories) {
        $this->categories = $categories;
    }

    function getCategories(): ?array {
        return $this->categories;
    }

    /**
     * @param VariationDTO[] $variations
     */
    function setVariations(array $variations) {
        $this->variations = $variations;
    }

    function getVariations(): ?array {
        return $this->variations;
    }

    function getDescription(): string {
        return $this->description;
    }

    function getLongDescription(): string {
        return $this->longDescription;
    }

    function setLongDescription(string $longDescription) {
        $this->longDescription = $longDescription;
    }

    /**
     * Serialize the product DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        $data = [
            'internalId' => $this->internalId,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'longDescription' => $this->longDescription,
            'formattedPrice' => $this->formattedPrice,
            'formattedPrevPrice' => $this->formattedPrevPrice,
            'useProductUrl' => $this->useProductUrl,
            'productUrl' => $this->productUrl,
            'addToCartUrl' => $this->addToCartUrl,
            'thumbnail' => $this->thumbnail,
            'medias' => $this->medias
        ];
        if ($this->stock !== null) {
            $data['stock'] = $this->stock;
        }
        if ($this->metafields !== null) {
            $data['metafields'] = $this->metafields;
        }
        if ($this->price !== null) {
            $data['price'] = $this->price;
        }
        if ($this->categories !== null) {
            $data['categories'] = $this->categories;
        }
        if ($this->variations !== null) {
            $data['variations'] = $this->variations;
        }
        return $data;
    }
}