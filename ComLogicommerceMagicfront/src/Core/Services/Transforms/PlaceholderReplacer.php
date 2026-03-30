<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Loads twig includes for each registered placeholder and replaces them
 * in widget templates while preserving the original line indentation.
 */
class PlaceholderReplacer {
    private static array $cache = [];

    /**
     * Replace all registered placeholders in the given template string.
     */
    public static function replace(string $tpl): string {
        foreach (self::getReplacements() as $needle => $replacement) {
            $tpl = self::replaceExactPreserveIndent($tpl, $needle, $replacement);
        }
        return $tpl;
    }

    /**
     * Return the placeholder => twig string map, loading includes on first call.
     */
    private static function getReplacements(): array {
        if (self::$cache !== []) {
            return self::$cache;
        }

        foreach (WidgetTemplatePlaceholder::cases() as $placeholder) {
            self::$cache[$placeholder->value] = self::loadInclude($placeholder->include());
        }

        return self::$cache;
    }

    /**
     * Read a twig include file from twigCoreTemplates/includes/.
     */
    private static function loadInclude(string $filename): string {
        $path = __DIR__ . '/../../../../twigCoreTemplates/includes/' . $filename;
        if (!is_file($path)) {
            throw new \RuntimeException("Widget template include not found: {$path}");
        }
        return file_get_contents($path) ?: '';
    }

    /**
     * Replace needle while preserving the line's leading indentation.
     */
    private static function replaceExactPreserveIndent(string $tpl, string $needle, string $replacement): string {
        $needleNorm = str_replace(["\r\n", "\r"], "\n", trim($needle));
        $tplNorm    = str_replace(["\r\n", "\r"], "\n", $tpl);

        if (!str_contains($tplNorm, $needleNorm)) {
            return $tpl;
        }

        $repLines = explode("\n", rtrim(str_replace(["\r\n", "\r"], "\n", $replacement), "\n"));
        $result   = [];

        foreach (explode("\n", $tplNorm) as $line) {
            $trimmedLine = ltrim($line, " \t");
            if (
                $trimmedLine === $needleNorm
                || str_starts_with($trimmedLine, $needleNorm . ' ')
                || str_starts_with($trimmedLine, $needleNorm . "\t")
            ) {
                $indent = substr($line, 0, strlen($line) - strlen($trimmedLine));
                foreach ($repLines as $repLine) {
                    $result[] = $repLine === '' ? '' : $indent . $repLine;
                }
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }
}
