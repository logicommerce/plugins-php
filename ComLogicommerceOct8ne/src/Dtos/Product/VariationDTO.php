<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Product;

/**
 * This is the product variation (combination) DTO class.
 *
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Product
 *
 * @see \JsonSerializable
 */
class VariationDTO implements \JsonSerializable {

    private string $internalId;

    private string $addToCartUrl = "";

    private string $thumbnail = "";

    private string $formattedPrice = "";

    private string $formattedPrevPrice = "";

    private array $stock = [];

    private array $options = [];

    private bool $default = false;

    public function __construct(string $internalId) {
        $this->internalId = $internalId;
    }

    function setAddToCartUrl(string $addToCartUrl) {
        $this->addToCartUrl = $addToCartUrl;
    }

    function getAddToCartUrl(): string {
        return $this->addToCartUrl;
    }

    function setThumbnail(string $thumbnail) {
        $this->thumbnail = $thumbnail;
    }

    function getThumbnail(): string {
        return $this->thumbnail;
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

    function setStock(array $stock) {
        $this->stock = $stock;
    }

    function getStock(): array {
        return $this->stock;
    }

    function setOptions(array $options) {
        $this->options = $options;
    }

    function getOptions(): array {
        return $this->options;
    }

    function setDefault(bool $default) {
        $this->default = $default;
    }

    function getDefault(): bool {
        return $this->default;
    }

    /**
     * Serialize the variation DTO
     *
     * @return array
     */
    public function jsonSerialize(): array {
        $data = [
            'internalId' => $this->internalId,
            'addToCartUrl' => $this->addToCartUrl,
            'stock' => $this->stock,
            'default' => $this->default,
            'options' => $this->options
        ];
        if ($this->thumbnail !== "") {
            $data['thumbnail'] = $this->thumbnail;
        }
        if ($this->formattedPrice !== "") {
            $data['formattedPrice'] = $this->formattedPrice;
            $data['formattedPrevPrice'] = $this->formattedPrevPrice;
        }
        return $data;
    }
}
