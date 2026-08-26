<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Transformers;

/**
 * Auto-injects `data-mff-child-index` + `data-mff-child-id` on the first HTML
 * element inside `{% for ... in page.subpages %}` loops, so the canvas can
 * pinpoint which child the user clicked. The id expression covers both
 * plugin (FWK Page: draftId) and preview (raw array: id) shapes.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig\Transformers
 */
class ChildIndexInjector {

    public const ATTR    = 'data-mff-child-index';
    public const ID_ATTR = 'data-mff-child-id';

    private const LOOP_VALUE = '"{{ loop.index0 }}"';

    public static function inject(string $tpl): string {
        $lines   = explode("\n", $tpl);
        $n       = count($lines);
        $changed = false;

        for ($i = 0; $i < $n; $i++) {
            $trimmed = trim($lines[$i]);
            if (!preg_match('/^\{%-?\s*for\s+(\w+)\s+in\s+page\.subpages\b/', $trimmed, $m)) {
                continue;
            }
            $varName   = $m[1];
            $targetIdx = self::findFirstHtmlElement($lines, $i + 1, $n);
            if ($targetIdx === null) {
                continue;
            }
            // Per-attribute idempotency: hand-written child-index must not block child-id injection.
            $hasAttr   = str_contains($lines[$targetIdx], self::ATTR);
            $hasIdAttr = str_contains($lines[$targetIdx], self::ID_ATTR);
            if ($hasAttr && $hasIdAttr) {
                continue;
            }
            $lines[$targetIdx] = self::addMissingAttributes(
                $lines[$targetIdx],
                $varName,
                !$hasAttr,
                !$hasIdAttr
            );
            $changed           = true;
        }

        return $changed ? implode("\n", $lines) : $tpl;
    }

    private static function findFirstHtmlElement(array $lines, int $start, int $n): ?int {
        for ($j = $start; $j < $n; $j++) {
            $t = trim($lines[$j]);
            if ($t === '' || str_starts_with($t, '{#') || str_starts_with($t, '{%')) {
                continue;
            }
            if (preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $t)) {
                return $j;
            }
            return null;
        }
        return null;
    }

    /** Id expression uses `is defined and X` (no `|default`, per project rule) for plugin + preview shapes. */
    private static function addMissingAttributes(string $line, string $varName, bool $needAttr, bool $needIdAttr): string {
        $parts = [];
        if ($needAttr) {
            $parts[] = self::ATTR . '=' . self::LOOP_VALUE;
        }
        if ($needIdAttr) {
            $idValue = sprintf(
                '"{{ %s.draftId is defined and %s.draftId ? %s.draftId : %s.id }}"',
                $varName,
                $varName,
                $varName,
                $varName
            );
            $parts[] = self::ID_ATTR . '=' . $idValue;
        }
        if (empty($parts)) {
            return $line;
        }
        $injection = ' ' . implode(' ', $parts);
        return preg_replace(
            '/(<[a-zA-Z][a-zA-Z0-9]*)([^>]*?)(\s*\/?>)/',
            '$1$2' . $injection . '$3',
            $line,
            1
        ) ?? $line;
    }
}
