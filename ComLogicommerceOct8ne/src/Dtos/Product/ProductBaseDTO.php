<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

/**
 * This is the product base DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 * 
 * @see \JsonSerializable
 */
class ProductBaseDTO implements \JsonSerializable {

    protected string $internalId="";

    protected string $reference="";

    protected string $title="";

    protected string $formattedPrice="";

    protected string $formattedPrevPrice="";

    protected string $productUrl="";

    protected string $thumbnail="";

    public function __construct(string $internalId, string $title) {
        $this->internalId = $internalId;
        $this->title = $title;
    }

    function setInternalId(string $internalId) {
        $this->internalId = $internalId;
    }

    function getInternalId(): string {
        return $this->internalId;
    }

    function setTitle(string $title) {
        $this->title = $title;
    }

    function getTitle(): string {
        return $this->title;
    }

    function setFormattedPrice(string $formattedPrice) {
        $this->formattedPrice = $formattedPrice;
    }

    function getFormattedPrice(): string {
        return $this->formattedPrice;
    }

    function setFormattedPrevPrice(string $formattedPrevPrice) {
        $this->formattedPrevPrice = $formattedPrevPrice;
    }

    function getFormattedPrevPrice(): string {
        return $this->formattedPrevPrice;
    }

    function setProductUrl(string $productUrl) {
        $this->productUrl = $productUrl;
    }

    function getProductUrl(): string {
        return $this->productUrl;
    }

    function setThumbnail(string $thumbnail) {
        $this->thumbnail = $thumbnail;
    }

    function getThumbnail(): string {
        return $this->thumbnail;
    }

    function setReference(string $reference) {
        $this->reference = $reference;
    }

    function getReference(): string {
        return $this->reference;
    }

    /**
     * Serialize the product base DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'internalId' => $this->internalId,
            'reference' => $this->reference,
            'title' => $this->title,
            'formattedPrice' => $this->formattedPrice,
            'formattedPrevPrice' => $this->formattedPrevPrice,
            'productUrl' => $this->productUrl,
            'thumbnail' => $this->thumbnail
        ];
    }
}