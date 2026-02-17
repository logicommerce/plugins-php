<?php

namespace Plugins\ComLogicommerceMagicfront\Services;

class WidgetTemplateTransformer {
    /**
     * Mapa: "texto exacto a buscar" => "texto de reemplazo"
     * Añade aquí futuros casos: '{{ dcs_widget2(page.subpages[i]) }}' => '...'
     */
    private const REPLACEMENTS = [
        '{{ dcs_widget }}' => <<<'TWIG'
{% import "macros/widget.twig" as widgetMacros %}
<!-- DCS_WIDGET_START {{ payload|json_encode|raw }} -->
<div id="{{ widgetId }}">
    {% if subPage.subpages is defined %}
        {{ widgetMacros.widgets({
            pages: subPage.subpages,
            version: version,
            widgetTemplateList: widgetTemplateList
        }) }}
    {% endif %}
</div>
<!-- DCS_WIDGET_END -->
TWIG,
        // Puedes agregar más replacements aquí: '{{ otro_widget }}' => '...'
    ];

    public static function transformAll(mixed $templates): mixed {
        return self::transformAny($templates);
    }

    private static function transformAny(mixed $value): mixed {
        if (is_string($value)) {
            return self::transformString($value);
        }

        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = self::transformAny($v);
            }
            return $value;
        }

        return $value;
    }

    private static function transformString(string $tpl): string {
        $tpl = str_replace(["\r\n", "\r"], "\n", $tpl);

        foreach (self::REPLACEMENTS as $needle => $replacement) {
            $tpl = self::replaceExactPreserveIndent($tpl, $needle, $replacement);
        }

        return $tpl;
    }

    /**
     * Replace needle while preserving indentation
     */
    private static function replaceExactPreserveIndent(string $tpl, string $needle, string $replacement): string {
        $needleNorm = str_replace(["\r\n", "\r"], "\n", trim($needle));
        $tplNorm = str_replace(["\r\n", "\r"], "\n", $tpl);

        // If needle not found, return template unchanged
        if (strpos($tplNorm, $needleNorm) === false) {
            return $tpl;
        }

        // Find and replace all occurrences with proper indentation
        $lines = explode("\n", $tplNorm);
        $result = [];

        foreach ($lines as $line) {
            $trimmedLine = ltrim($line, " \t");

            // Check if this line contains the needle
            if ($trimmedLine === $needleNorm || str_starts_with($trimmedLine, $needleNorm . ' ') || str_starts_with($trimmedLine, $needleNorm . "\t")) {
                // Calculate indentation
                $indent = substr($line, 0, strlen($line) - strlen($trimmedLine));

                // Apply indentation to replacement
                $rep = str_replace(["\r\n", "\r"], "\n", $replacement);
                $repLines = explode("\n", rtrim($rep, "\n"));

                foreach ($repLines as $repLine) {
                    $result[] = ($repLine === '' ? '' : $indent . $repLine);
                }
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }
}
