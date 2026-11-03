<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

/**
 * Array normalizer for the widget data CARRIERS.
 *
 * `json_encode()` only calls `jsonSerialize()` on objects that declare `\JsonSerializable`, which in the
 * SDK means the ones extending {@see \SDK\Core\Dtos\Element}. DTOs that only `use ElementTrait` — such as
 * {@see \SDK\Dtos\Basket\BasketWarnings\BasketWarning}, the type behind `warningsBlockItems` — are NOT
 * `JsonSerializable`, so `json_encode()` falls back to their PUBLIC properties and emits `{}`. Nested in an
 * Element they are fine (`ElementTrait::getFormattedData()` calls `toArray()` on them), but a view helper
 * that returns them at the top level of a plain array loses them entirely.
 *
 * Walking the value with the DTO's OWN `toArray()` keeps the carrier faithful: the widget still receives
 * the complete object, never a hand-picked subset.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class DtoArray {

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public static function normalize($value) {
        if (is_object($value) && !$value instanceof \JsonSerializable && method_exists($value, 'toArray')) {
            $value = $value->toArray();
        }
        if (is_array($value)) {
            return array_map(static fn($item) => self::normalize($item), $value);
        }
        return $value;
    }
}
