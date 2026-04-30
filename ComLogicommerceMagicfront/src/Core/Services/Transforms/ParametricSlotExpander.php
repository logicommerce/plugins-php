<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

/**
 * Rewrites each `{{ mff_widget_slot('id') }}` into a filter-by-slotId Twig
 * block that renders the matching child widget via `widgetMacros.widgets`.
 *
 * Context-aware: outside any `{% for %}` the filter iterates `page.subpages`;
 * inside one, it iterates `subPage.subpages`. The emitted block imports the
 * macros file explicitly because the placeholder is expanded inside a
 * `{% include template_from_string(...) %}` block (from widgets.html.twig),
 * which does not inherit parent macro imports.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services\Transforms
 */
final class ParametricSlotExpander {

    // Matches the parametric form only: `{{ mff_widget_slot('id') }}` with
    // either quote style.
    private const PATTERN = '/\{\{-?\s*mff_widget_slot\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)\s*-?\}\}/';

    public static function expand(string $tpl): string {
        if (preg_match(self::PATTERN, $tpl) !== 1) {
            return $tpl;
        }
        $lines       = explode("\n", $tpl);
        $output      = [];
        $needsImport = false;
        $depth       = 0; // `{% for %}` nesting depth.

        foreach ($lines as $line) {
            // Update for-loop depth BEFORE checking for the placeholder, so a
            // placeholder on the same line as `{% for %}` is considered inside.
            $openCount  = preg_match_all('/\{%-?\s*for\s/', $line);
            $closeCount = preg_match_all('/\{%-?\s*endfor\s*-?%\}/', $line);
            $depth     += ($openCount !== false ? $openCount : 0);

            if (preg_match(self::PATTERN, $line)) {
                $needsImport = true;
                $inForLoop   = $depth > 0;
                $line        = self::rewriteLine($line, $inForLoop);
            }
            $output[] = $line;

            $depth -= ($closeCount !== false ? $closeCount : 0);
            if ($depth < 0) {
                $depth = 0;
            }
        }
        if ($needsImport) {
            array_unshift($output, "{% import 'macros/widget.twig' as __mffSlotMacros %}");
        }
        return implode("\n", $output);
    }

    private static function rewriteLine(string $line, bool $inForLoop): string {
        $indent = substr($line, 0, strlen($line) - strlen(ltrim($line, " \t")));
        return preg_replace_callback(
            self::PATTERN,
            static function (array $m) use ($inForLoop, $indent): string {
                $slotId = addslashes($m[1]);
                $source = $inForLoop ? 'subPage.subpages' : 'page.subpages';
                // Rebind `subPage` inside the inner for-loop so the rendered
                // macro receives the matched child. Twig loop scoping restores
                // the outer `subPage` automatically at `{% endfor %}`.
                return '{% for __sp in ' . $source . ' %}'
                    . '{% if __sp.slotId == \'' . $slotId . '\' %}'
                    . "\n" . $indent . '    ' . '{% set subPage = __sp %}'
                    . "\n" . $indent . '    ' . '{{ __mffSlotMacros.widgets({pages: [subPage], version: version, widgetTemplateList: widgetTemplateList}) }}'
                    . "\n" . $indent . '{% endif %}{% endfor %}';
            },
            $line
        ) ?? $line;
    }
}
