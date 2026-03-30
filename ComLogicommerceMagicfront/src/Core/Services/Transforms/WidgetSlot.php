<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Injects mff-widget-slot-root="1" onto the second-level HTML parent
 * of the {{ mff_widget_slot }} block inside a widget twig template.
 *
 * Normal case (2 HTML parents exist):
 *   <outer>          ← 2nd parent → gets mff-widget-slot-root="1"
 *       <inner>      ← 1st parent
 *           {% for %}
 *               {{ mff_widget_slot }}
 *           {% endfor %}
 *       </inner>
 *   </outer>
 *
 * Fallback (only 1 HTML parent):
 *   A new <div mff-widget-slot-root="1"> is injected to wrap the 1st parent.
 */
class WidgetSlot {
    public const WIDGET_SLOT_ROOT = 'mff-widget-slot-root';

    // HTML phrasing elements that cannot contain block-level content.
    // When these are the only HTML parent of the slot block, treat as Case C.
    private const PHRASING_ELEMENTS = [
        'p', 'span', 'a', 'b', 'em', 'strong', 'i', 'u', 's',
        'small', 'label', 'li', 'dt', 'dd', 'td', 'th',
    ];

    public static function inject(string $tpl): string {
        $lines = explode("\n", $tpl);
        $n     = count($lines);

        [$slotStart, $slotEnd] = self::findSlotBlock($lines, $n);
        if ($slotStart === null) {
            return $tpl;
        }

        [$firstIdx, $firstIndentLen, $firstIndentStr, $firstTagName, $secondIdx]
            = self::findHtmlParents($lines, $slotStart);

        // Case A: 2nd parent exists — add attribute directly.
        if ($secondIdx !== null) {
            $lines[$secondIdx] = preg_replace(
                '/(<[a-zA-Z][a-zA-Z0-9]*)([^>]*)(>)/',
                '$1$2 ' . self::WIDGET_SLOT_ROOT . '="1"$3',
                $lines[$secondIdx],
                1
            ) ?? $lines[$secondIdx];

            return implode("\n", $lines);
        }

        // Case C: no HTML parent at all, or 1st parent is a phrasing element that cannot
        // contain block-level content (e.g. <p>) — inject two wrapper divs around the for
        // block, matching the normal two-level structure (outer=slot-root, inner=1st parent).
        if ($firstIdx === null || in_array($firstTagName, self::PHRASING_ELEMENTS, true)) {
            $indent = substr($lines[$slotStart], 0, strlen($lines[$slotStart]) - strlen(ltrim($lines[$slotStart], " \t")));
            // Higher index first to avoid offset shifts.
            array_splice($lines, $slotEnd + 1, 0, [$indent . '</div>', $indent . '</div>']);
            array_splice($lines, $slotStart,   0, [$indent . '<div ' . self::WIDGET_SLOT_ROOT . '="1">', $indent . '<div>']);
            return implode("\n", $lines);
        }

        // Case B: only 1st parent — inject a <div> grandparent.

        $closingIdx = self::findClosingTag($lines, $n, $firstIdx, $firstIndentLen, $firstTagName);
        if ($closingIdx === null) {
            return $tpl;
        }

        array_splice($lines, $closingIdx + 1, 0, [$firstIndentStr . '</div>']);
        array_splice($lines, $firstIdx,       0, [$firstIndentStr . '<div ' . self::WIDGET_SLOT_ROOT . '="1">']);

        return implode("\n", $lines);
    }

    /**
     * Find the line range of the slot block.
     * Tries {% for %}...{% endfor %} first, then standalone line.
     *
     * @return array{int|null, int|null}
     */
    private static function findSlotBlock(array $lines, int $n): array {
        for ($i = 0; $i < $n; $i++) {
            $trimmed = trim($lines[$i]);
            if (!preg_match('/^\{%-?\s*for\s/', $trimmed)) {
                continue;
            }
            $foundSlot = false;
            for ($j = $i + 1; $j < $n; $j++) {
                $inner = trim($lines[$j]);
                if (str_contains($inner, WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value)) {
                    $foundSlot = true;
                }
                if (preg_match('/^\{%-?\s*endfor\s*-?%\}$/', $inner)) {
                    if ($foundSlot) return [$i, $j];
                    break;
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            if (str_contains(trim($lines[$i]), WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value)) {
                return [$i, $i];
            }
        }

        return [null, null];
    }

    /**
     * Walk upward from slotStart collecting the 1st and 2nd HTML parent lines.
     *
     * @return array{int|null, int|null, string|null, string|null, int|null}
     */
    private static function findHtmlParents(array $lines, int $slotStart): array {
        $currentIndent = strlen($lines[$slotStart]) - strlen(ltrim($lines[$slotStart], " \t"));
        $firstIdx      = null;
        $firstIndentLen = null;
        $firstIndentStr = null;
        $firstTagName  = null;
        $secondIdx     = null;

        for ($i = $slotStart - 1; $i >= 0; $i--) {
            $line    = $lines[$i];
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            $indentLen = strlen($line) - strlen(ltrim($line, " \t"));
            if ($indentLen >= $currentIndent) continue;
            if (!preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $trimmed, $m)) continue;

            if ($firstIdx === null) {
                $firstIdx      = $i;
                $firstIndentLen = $indentLen;
                $firstIndentStr = substr($line, 0, $indentLen);
                $firstTagName  = strtolower($m[1]);
            } else {
                $secondIdx = $i;
                break;
            }

            $currentIndent = $indentLen;
        }

        return [$firstIdx, $firstIndentLen, $firstIndentStr, $firstTagName, $secondIdx];
    }

    /**
     * Find the closing tag of an element by matching indentation and tag name.
     */
    private static function findClosingTag(array $lines, int $n, int $fromIdx, int $indentLen, string $tagName): ?int {
        for ($i = $fromIdx + 1; $i < $n; $i++) {
            $trimmed       = trim($lines[$i]);
            $lineIndentLen = strlen($lines[$i]) - strlen(ltrim($lines[$i], " \t"));
            if ($lineIndentLen === $indentLen && $trimmed === '</' . $tagName . '>') {
                return $i;
            }
        }
        return null;
    }
}
