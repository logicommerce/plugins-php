<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Filter;

/**
 * This is the applied filter DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Filter
 * 
 * @see \JsonSerializable
 */
class AppliedFilterDTO implements \JsonSerializable {

    private string $param;

    private string $paramLabel;

    private string $value;

    private string $valueLabel;

    public function __construct(string $param, string $paramLabel, string $value, string $valueLabel) {
        $this->param = $param;
        $this->paramLabel = $paramLabel;
        $this->value = $value;
        $this->valueLabel = $valueLabel;
    }

    public function getParam(): string {
        return $this->param;
    }

    public function getParamLabel(): string {
        return $this->paramLabel;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getValueLabel(): string {
        return $this->valueLabel;
    }

    public function setParam(string $param): void {
        $this->param = $param;
    }

    public function setParamLabel(string $paramLabel): void {
        $this->paramLabel = $paramLabel;
    }

    public function setValue(string $value): void {
        $this->value = $value;
    }

    public function setValueLabel(string $valueLabel): void {
        $this->valueLabel = $valueLabel;
    }

    /**
     * Serialize the applied filter DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'param' => $this->param,
            'paramLabel' => $this->paramLabel,
            'value' => $this->value,
            'valueLabel' => $this->valueLabel
        ];
    }
}