<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\FilterInput\FilterInput;
use FWK\Core\FilterInput\FilterInputHandler;
use SDK\Core\Resources\Date;

/**
 * The plugin's ONE reader of the request query string (#4285). Every value goes through an FWK {@see FilterInput}:
 * the one the FWK declares for that parameter ({@see \FWK\Core\FilterInput\FilterInputFactory}) when it has one, else
 * one of the shapes below. A value the filter rejects reads as absent, so the caller keeps its default.
 *
 * Why: a hand-written or stale URL (`?orderBy=xxx`, `?sort=<script>`, `?brandsList[]=abc`, `?category[]=-5`) reached
 * the SDK raw, its validator threw when the batch was built (S130I00002) and the store answered 500. With the filter in
 * front, the buyer gets the normal page with that parameter ignored, as the FWK's own controllers do.
 *
 * `$_GET` is read here and nowhere else in the plugin: QueryReadersTest fails on any other read.
 *
 * Storefront only (FWK + SDK): the docker renderer does not load Core/Resources, so code under Core/Twig reaches this
 * class only behind a `class_exists(RenderMode::class)` guard.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
final class RequestQuery {

    /** A positive integer, the SDK's rule for ids and pages ({@see \SDK\Core\Services\Parameters\Validators\ParametersValidator}). */
    public static function positiveInt(): FilterInput {
        return new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false,
            FilterInput::CONFIGURATION_FILTER_KEY_FILTER_VALIDATE_ID => FILTER_VALIDATE_INT,
            FilterInput::CONFIGURATION_FILTER_KEY_FILTER_FLAGS => ['options' => ['min_range' => 1]],
        ]);
    }

    /**
     * One of $values, or several of them comma separated (`statusIdList=INCOMING,COMPLETED`), as the FWK's own forms
     * declare their lists ({@see \FWK\Core\Form\Elements\MultiSelect}): the FWK splits the value by commas and one item
     * outside $values rejects the whole value. Case sensitive, like the FWK.
     *
     * @param list<string> $values
     */
    public static function oneOf(array $values): FilterInput {
        return new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false,
            FilterInput::CONFIGURATION_FILTER_KEY_AVAILABLE_VALUES => $values,
        ]);
    }

    /** A yes/no flag (`1`/`0`, `true`/`false`, `on`/`off`, `yes`/`no`), validated as the FWK validates its booleans. */
    public static function flag(): FilterInput {
        return new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false,
            FilterInput::CONFIGURATION_FILTER_KEY_FILTER_VALIDATE_ID => FILTER_VALIDATE_BOOLEAN,
        ]);
    }

    /** Free text, such as a search: the FWK filter strips its tags and trims it. */
    public static function text(): FilterInput {
        return new FilterInput();
    }

    /**
     * A value that must arrive as sent (a token, a name, a case of an enum): rejected if the sanitizing filter would
     * change it. The FWK still strips tags, decodes entities and trims spaces before comparing
     * ({@see FilterInput::getFilterValue()}), and those changes pass without a word: `?mfToken=%20tok%20` reads `tok`.
     */
    public static function exact(): FilterInput {
        return new FilterInput([FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false]);
    }

    /** A value that matches $pattern, untouched. */
    public static function matching(string $pattern): FilterInput {
        return new FilterInput([
            FilterInput::CONFIGURATION_FILTER_KEY_ENABLE_MODIFICATION => false,
            FilterInput::CONFIGURATION_FILTER_KEY_REGEX_VALIDATE => $pattern,
        ]);
    }

    /**
     * Whether the query string carries $name at all, before any filter: lets a caller skip building the FWK filter
     * for a parameter the address does not have, on a path that also runs without the FWK (the plugins CI has only
     * the SDK, and SpecialPageExistsTest runs PluginProperties with stand-ins).
     */
    public static function has(string $name): bool {
        return array_key_exists($name, $_GET);
    }

    /** One value: null when absent, empty, sent as a list (`x[]=`) or rejected by $filter. */
    public static function value(string $name, FilterInput $filter): ?string {
        return self::filtered($_GET[$name] ?? null, $filter);
    }

    /**
     * A list (`x[]=a&x[]=b`, or a single `x=a`): the values $filter accepts, the rest dropped.
     *
     * @return list<string>
     */
    public static function values(string $name, FilterInput $filter): array {
        $raw = $_GET[$name] ?? null;
        $kept = [];
        foreach (is_array($raw) ? $raw : [$raw] as $item) {
            $value = self::filtered($item, $filter);
            if ($value !== null) {
                $kept[] = $value;
            }
        }
        return $kept;
    }

    /**
     * The dynamic-name parameters `<prefix>_<identifier>` (`filterOption_Color[]=Rojo`), recognised by the FWK's own
     * rule ({@see FilterInputHandler::getAvailableDinamicParam()}): identifier → the values $filter accepts. An
     * identifier $identifierFilter rejects, or one left with no value, is dropped.
     *
     * The values are not read with the FWK's {@see FilterInputHandler::getFilterFilterInputs()}: a single value
     * (`filterOption_Color=Rojo`) loses its identifier there, and next to a list of the same prefix
     * (`&filterOption_Talla[]=M`) it throws a TypeError, a 500. Here a single value counts as a list of one.
     *
     * @return array<string, list<string>>
     */
    public static function dynamic(string $prefix, FilterInput $identifierFilter, FilterInput $filter): array {
        $found = [];
        foreach (array_keys($_GET) as $key) {
            $parts = FilterInputHandler::getAvailableDinamicParam((string) $key);
            if ($parts === null || $parts[0] !== $prefix) {
                continue;
            }
            $identifier = self::filtered($parts[1], $identifierFilter);
            $values = $identifier === null ? [] : self::values((string) $key, $filter);
            if ($values !== []) {
                $found[$identifier] = $values;
            }
        }
        return $found;
    }

    /**
     * A date, `2026-10-09` or ATOM, parsed by the SDK as the FWK's own account controllers parse theirs
     * ({@see Date::create()}): null when absent or not a date. Also null when the parse rolled it over to a date the SDK
     * then refuses: `0000-00-00` becomes -0001-11-30 and `9999-99-99` the year 10007, and the SDK's validator, which
     * reads the date back with the same Date::create, threw when the batch was built (the page answered 500). A date
     * that rolls over but reads back (`2026-02-31` → 2026-03-03) is kept, as the FWK keeps it.
     */
    public static function date(string $name): ?\DateTime {
        $value = self::value($name, self::exact());
        $date = $value === null ? null : Date::create($value)?->getDateTime();
        if ($date === null || Date::create($date->format(Date::DATETIME_FORMAT)) === null) {
            return null;
        }
        return $date;
    }

    private static function filtered(mixed $raw, FilterInput $filter): ?string {
        if ($raw === null || is_array($raw)) {
            return null;
        }
        $value = $filter->getFilterValue((string) $raw);
        return is_string($value) && $value !== '' ? $value : null;
    }
}
