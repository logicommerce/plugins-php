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
        return [
            'internalId' => $this->internalId,
            'reference' => $this->reference,
            'title' => $this->title,
            'description' => $this->description,
            'longDescription' => $this->longDescription,
            'formattedPrice' => $this->formattedPrice,
            'formattedPrevPrice' => $this->formattedPrevPrice,
            'useProductUrl' => $this->useProductUrl,
            'productUrl' => $this->productUrl,
            //'addToCartUrl' => $this->addToCartUrl,
            'thumbnail' => $this->thumbnail,
            'medias' => $this->medias
        ];
    }
}