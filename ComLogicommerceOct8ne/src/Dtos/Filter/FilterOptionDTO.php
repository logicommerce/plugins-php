<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Filter;

/**
 * This is the filter option DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Filter
 * 
 * @see \JsonSerializable
 */
class FilterOptionDTO implements \JsonSerializable {

    private string $value = "";

    private string $valueLabel = "";

    private int $count = 0;

    public function __construct(string $value, string $valueLabel, int $count=0) {
        $this->value = $value;
        $this->valueLabel = $valueLabel;
        $this->count = $count;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getValueLabel(): string {
        return $this->valueLabel;
    }

    public function getCount(): int {
        return $this->count;
    }

    public function setValue(string $value): void {
        $this->value = $value;
    }

    public function setValueLabel(string $valueLabel): void {
        $this->valueLabel = $valueLabel;
    }

    public function setCount(int $count): void {
        $this->count = $count;
    }

    /**
     * Serialize the filter option DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'value' => $this->value,
            'valueLabel' => $this->valueLabel,
            'count' => $this->count
        ];
    }

}