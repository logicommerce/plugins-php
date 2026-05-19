<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

/**
 * Auto-injects two targeting attributes on the first HTML element inside
 * `{% for ... in page.subpages %}` loops:
 *
 *   - `data-mff-child-index="{{ loop.index0 }}"` — used by the editor's
 *     instance CSS scoping (per-child slot) and as a positional fallback.
 *   - `data-mff-child-id="..."` — carries the actual child widget id so the
 *     canvas can tell the editor exactly which child the user clicked,
 *     without relying on positional indices that shift on reorder/insert.
 *
 * The id expression handles both renderer paths in one form:
 *   - **Plugin** (FWK Page): `subpage.draftId` is the UUID, `subpage.id` is 0
 *   - **Preview** (raw arrays): `subpage.id` is the UUID, no `draftId`
 *
 * Widget authors can write clean loops without worrying about the canvas
 * targeting attributes; the transformer adds them.
 *
 * Example input:
 *   {% for subpage in page.subpages %}
 *     <div data-mff-el="item">...</div>
 *   {% endfor %}
 *
 * After transform:
 *   {% for subpage in page.subpages %}
 *     <div data-mff-el="item"
 *          data-mff-child-index="{{ loop.index0 }}"
 *          data-mff-child-id="{{ subpage.draftId is defined and subpage.draftId ? subpage.draftId : subpage.id }}">...</div>
 *   {% endfor %}
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
            // Idempotent per-attribute: only inject the attrs that are
            // actually missing. Some widget authors hand-write
            // `data-mff-child-index` (e.g. when they want it visible in the
            // template source) and previously that would short-circuit the
            // whole injection, leaving `data-mff-child-id` absent — which
            // breaks the canvas's child-id-scoped selectors and stops
            // inline-edit from activating on per-child elements.
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

    /**
     * Append the missing targeting attributes to the first opening tag on
     * the line. The id expression uses Twig's `is defined and X` pattern
     * (no `|default(...)` per project rule) so it works whether `draftId`
     * is present (plugin Page) or absent (raw array on the preview
     * renderer).
     */
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
