<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Filter;

/**
 * This is the filter info DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Filter
 * 
 * @see \JsonSerializable
 */
class FilterInfoDTO implements \JsonSerializable {

    private array $applied = [];

    private array $available = [];

    public function __construct() {
    }

    public function getApplied(): array {
        return $this->applied;
    }

    public function getAvailable(): array {
        return $this->available;
    }

    public function setApplied(array $applied): void {
        $this->applied = $applied;
    }

    public function setAvailable(array $available): void {
        $this->available = $available;
    }

    /**
     * Serialize the filter info DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'applied' => $this->applied,
            'available' => $this->available
        ];
    }
}