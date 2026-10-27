<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

/**
 * Which of the costly `shared.*` keys the widget templates of a render actually read (#4251).
 *
 * The countries, their names and the default country's locations cost API calls on every page (the country list
 * twice and the locations, which answer 403 to a visitor), yet only the blocks that ask for an address or a place
 * read them. Rather than a fixed list of widget families in PHP — which would leave a merchant's own block without
 * its data — each template declares what it needs by reading it: `shared.countries`, `shared['locationData']`…
 *
 * Only Twig code counts (`{{ … }}` and `{% … %}`): the word «shared» in markup or in a script comment is not a read.
 * Using `shared` whole (`{% set s = shared %}`, `shared|json_encode`, `attribute(shared, k)`) could read anything,
 * so it asks for every key; testing it (`shared is defined`) reads nothing. Handing it to a child render
 * (`{shared: shared}`) does not ask either: the child's own template is in the same list.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
final class SharedNeeds {

    /** The `shared.*` keys built only when a template reads them; every other key is cheap and always built. */
    public const ON_DEMAND = ['countries', 'countryNames', 'locationData'];

    private const TWIG_CODE = '/\{\{.*?\}\}|\{%.*?%\}/s';

    /** `shared` as a variable: not a property of something else (`x.shared`) nor inside a quoted name. */
    private const VARIABLE = '(?<![.\w\'"$-])shared\b';

    private function __construct() {
    }

    /**
     * @param iterable<mixed> $templates Twig sources (non-strings are ignored)
     * @return string[] the {@see self::ON_DEMAND} keys some template reads, in that order
     */
    public static function of(iterable $templates): array {
        $needed = [];
        foreach ($templates as $template) {
            if (!is_string($template) || $template === '' || !str_contains($template, 'shared')) {
                continue;
            }
            preg_match_all(self::TWIG_CODE, $template, $blocks);
            foreach ($blocks[0] as $code) {
                if (self::readsWhole($code)) {
                    return self::ON_DEMAND;
                }
                foreach (self::ON_DEMAND as $key) {
                    if (!isset($needed[$key]) && preg_match(self::keyPattern($key), $code) === 1) {
                        $needed[$key] = true;
                    }
                }
            }
        }
        return array_values(array_filter(self::ON_DEMAND, static fn(string $key): bool => isset($needed[$key])));
    }

    /** `shared` used as a whole value: not followed by `.x` / `['x']`, not a hash key, not handed on as `shared: shared`. */
    private static function readsWhole(string $code): bool {
        preg_match_all('/' . self::VARIABLE . '/', $code, $uses, PREG_OFFSET_CAPTURE);
        foreach ($uses[0] as [, $offset]) {
            $before = substr($code, 0, $offset);
            $after  = substr($code, $offset + strlen('shared'));
            if (preg_match('/^\s*[.\[:]/', $after) === 1) {
                continue; // shared.x, shared['x'], or the key of a hash ({shared: …})
            }
            if (preg_match('/^\s+is\s+(?:not\s+)?(?:defined|null|none|empty|iterable)\b/', $after) === 1) {
                continue; // a test on the container reads none of its keys
            }
            if (preg_match('/\bshared\s*:\s*$/', $before) === 1) {
                continue; // the value of {shared: shared}: a child render, whose template is in the list
            }
            return true;
        }
        return false;
    }

    private static function keyPattern(string $key): string {
        $quoted = preg_quote($key, '/');
        return '/' . self::VARIABLE . '\s*(?:\.\s*' . $quoted . '\b|\[\s*([\'"])' . $quoted . '\1\s*\])/';
    }
}
