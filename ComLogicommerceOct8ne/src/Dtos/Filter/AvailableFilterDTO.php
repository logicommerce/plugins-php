<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Filter;

/**
 * This is the available filter DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Filter
 * 
 * @see \JsonSerializable
 */
class AvailableFilterDTO implements \JsonSerializable {

    private string $param;

    private string $paramLabel;

    private array $options = [];

    public function __construct(string $param, string $paramLabel) {
        $this->param = $param;
        $this->paramLabel = $paramLabel;
    }

    public function getParam(): string {
        return $this->param;
    }

    public function getParamLabel(): string {
        return $this->paramLabel;
    }

    public function getOptions(): array {
        return $this->options;
    }

    public function setParam(string $param): void {
        $this->param = $param;
    }

    public function setParamLabel(string $paramLabel): void {
        $this->paramLabel = $paramLabel;
    }

    public function setOptions(array $options): void {
        $this->options = $options;
    }

    /**
     * Serialize the available filter DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'param' => $this->param,
            'paramLabel' => $this->paramLabel,
            'options' => $this->options
        ];
    }
}