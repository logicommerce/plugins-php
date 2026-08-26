<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Form;

/**
 * @package Plugins\ComLogicommerceMagicfront\Core\Form
 */
class WidgetFieldConfig {

    public static function fromPropertyValues(array $propertyValues): array {
        $userTypes = [];
        $fields = [];
        foreach ($propertyValues as $property) {
            $propertyId = $property['propertyId'] ?? '';
            if (!is_string($propertyId) || $propertyId === '') {
                continue;
            }
            if (($property['enabled'] ?? true) === false) {
                continue;
            }
            $value = $property['value'] ?? null;
            if (preg_match('/^ut_(PARTICULAR|BUSINESS|FREELANCE)_(included|priority)$/', $propertyId, $m) === 1) {
                $userTypes[$m[1]] ??= [];
                $userTypes[$m[1]][$m[2]] = $m[2] === 'priority' ? self::coerceInt($value) : self::coerceBool($value);
                continue;
            }
            if (preg_match('/^f_(PARTICULAR|BUSINESS|FREELANCE)_(.+)_(included|required|priority)$/', $propertyId, $m) === 1) {
                $type = $m[1];
                $key = $m[2];
                $attr = $m[3];
                $fields[$type] ??= [];
                $fields[$type][$key] ??= ['key' => $key];
                $fields[$type][$key][$attr] = $attr === 'priority' ? self::coerceInt($value) : self::coerceBool($value);
            }
        }
        $config = [];
        if ($userTypes !== []) {
            $config['userTypes'] = $userTypes;
        }
        if ($fields !== []) {
            $config['fields'] = array_map('array_values', $fields);
        }
        return $config;
    }

    public static function fromModuleSettings(array $moduleSettings): array {
        return self::fromPropertyValues(self::moduleSettingsToPropertyValues($moduleSettings));
    }

    public static function editFromPropertyValues(array $propertyValues): array {
        $account = [];
        $registeredUser = [];
        foreach ($propertyValues as $property) {
            $propertyId = $property['propertyId'] ?? '';
            if (!is_string($propertyId) || $propertyId === '') {
                continue;
            }
            if (($property['enabled'] ?? true) === false) {
                continue;
            }
            $value = $property['value'] ?? null;
            if (preg_match('/^af_(.+)_(included|required|priority)$/', $propertyId, $m) === 1) {
                $key = $m[1];
                $attr = $m[2];
                $account[$key] ??= ['key' => $key];
                $account[$key][$attr] = $attr === 'priority' ? self::coerceInt($value) : self::coerceBool($value);
                continue;
            }
            if (preg_match('/^ruf_(.+)_(included|required|priority)$/', $propertyId, $m) === 1) {
                $key = $m[1];
                $attr = $m[2];
                $registeredUser[$key] ??= ['key' => $key];
                $registeredUser[$key][$attr] = $attr === 'priority' ? self::coerceInt($value) : self::coerceBool($value);
            }
        }
        $config = [];
        if ($account !== []) {
            $config['account'] = array_values($account);
        }
        if ($registeredUser !== []) {
            $config['registeredUser'] = array_values($registeredUser);
        }
        return $config;
    }

    public static function editFromModuleSettings(array $moduleSettings): array {
        return self::editFromPropertyValues(self::moduleSettingsToPropertyValues($moduleSettings));
    }

    private static function moduleSettingsToPropertyValues(array $moduleSettings): array {
        $propertyValues = [];
        foreach ($moduleSettings as $propertyId => $value) {
            $propertyValues[] = ['propertyId' => $propertyId, 'value' => $value];
        }
        return $propertyValues;
    }

    private static function coerceBool(mixed $value): bool {
        if (is_bool($value)) {
            return $value;
        }
        return $value === 'true' || $value === '1' || $value === 1;
    }

    private static function coerceInt(mixed $value): int {
        return is_numeric($value) ? (int) $value : 0;
    }
}
