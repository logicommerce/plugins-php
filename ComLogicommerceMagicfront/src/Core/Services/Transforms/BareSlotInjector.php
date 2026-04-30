<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Services\Transforms;

use Plugins\ComLogicommerceMagicfront\Enums\WidgetTemplatePlaceholder;

/**
 * Injects `mff-widget-slot-root="1"` onto the appropriate HTML parent of a
 * bare `{{ mff_widget_slot }}` block so canvas can detect the slot root.
 * The attribute targets the **2nd HTML ancestor** of the slot placeholder.
 *
 * Three cases (mirrors the docker renderer):
 *
 * ─── Case A ── two HTML ancestors exist → add attribute to the 2nd ──────
 *   Input:                              Output:
 *     <section>                           <section mff-widget-slot-root="1">
 *       <div>                               <div>
 *         {% for c in subpages %}             {% for c in subpages %}
 *           {{ mff_widget_slot }}               {{ mff_widget_slot }}
 *         {% endfor %}                        {% endfor %}
 *       </div>                              </div>
 *     </section>                          </section>
 *
 * ─── Case B ── only one HTML ancestor → wrap it with a new <div> ────────
 *   Input:                              Output:
 *     <div>                               <div mff-widget-slot-root="1">
 *       {% for c in subpages %}             <div>
 *         {{ mff_widget_slot }}               {% for c in subpages %}
 *       {% endfor %}                          {{ mff_widget_slot }}
 *     </div>                                {% endfor %}
 *                                         </div>
 *                                       </div>
 *
 * ─── Case C ── no ancestor, or ancestor is phrasing (<p>, <span>…) ──────
 *   Input:                              Output:
 *     <p>                                 <p>
 *       {% for c in subpages %}             <div mff-widget-slot-root="1">
 *         {{ mff_widget_slot }}               <div>
 *       {% endfor %}                          {% for c in subpages %}
 *     </p>                                      {{ mff_widget_slot }}
 *                                           {% endfor %}
 *                                         </div>
 *                                       </div>
 *                                     </p>
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Services\Transforms
 */
final class BareSlotInjector {

    /**
     * HTML phrasing elements that cannot contain block-level content. When
     * one of these is the slot block's only HTML ancestor, case C is used.
     */
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

        if ($secondIdx !== null) {
            return self::applyCaseA($lines, $secondIdx);
        }
        if ($firstIdx === null || in_array($firstTagName, self::PHRASING_ELEMENTS, true)) {
            return self::applyCaseC($lines, $slotStart, $slotEnd);
        }
        return self::applyCaseB($lines, $n, $firstIdx, $firstIndentLen, $firstIndentStr, $firstTagName, $tpl);
    }

    /** Case A: add the attribute directly to an existing 2nd HTML parent. */
    private static function applyCaseA(array $lines, int $secondIdx): string {
        $lines[$secondIdx] = preg_replace(
            '/(<[a-zA-Z][a-zA-Z0-9]*)([^>]*)(>)/',
            '$1$2 ' . WidgetSlot::WIDGET_SLOT_ROOT . '="1"$3',
            $lines[$secondIdx],
            1
        ) ?? $lines[$secondIdx];
        return implode("\n", $lines);
    }

    /** Case B: wrap the single existing parent with a new slot-root `<div>`. */
    private static function applyCaseB(
        array $lines,
        int $n,
        int $firstIdx,
        int $firstIndentLen,
        string $firstIndentStr,
        string $firstTagName,
        string $originalTpl
    ): string {
        $closingIdx = self::findClosingTag($lines, $n, $firstIdx, $firstIndentLen, $firstTagName);
        if ($closingIdx === null) {
            return $originalTpl;
        }
        array_splice($lines, $closingIdx + 1, 0, [$firstIndentStr . '</div>']);
        array_splice($lines, $firstIdx, 0, [$firstIndentStr . '<div ' . WidgetSlot::WIDGET_SLOT_ROOT . '="1">']);
        return implode("\n", $lines);
    }

    /** Case C: wrap the whole for-block with two `<div>`s. */
    private static function applyCaseC(array $lines, int $slotStart, int $slotEnd): string {
        $indent = substr($lines[$slotStart], 0, strlen($lines[$slotStart]) - strlen(ltrim($lines[$slotStart], " \t")));
        // Insert end markers first so the slotStart index stays valid.
        array_splice($lines, $slotEnd + 1, 0, [$indent . '</div>', $indent . '</div>']);
        array_splice($lines, $slotStart, 0, [
            $indent . '<div ' . WidgetSlot::WIDGET_SLOT_ROOT . '="1">',
            $indent . '<div>',
        ]);
        return implode("\n", $lines);
    }

    /**
     * Find the line range of the slot block. Tries `{% for %}...{% endfor %}`
     * containing a bare `{{ mff_widget_slot }}` first, then a standalone line.
     *
     * @return array{int|null, int|null}
     */
    private static function findSlotBlock(array $lines, int $n): array {
        $placeholder = WidgetTemplatePlaceholder::MFF_WIDGET_SLOT->value;

        for ($i = 0; $i < $n; $i++) {
            if (!preg_match('/^\{%-?\s*for\s/', trim($lines[$i]))) {
                continue;
            }
            $foundSlot = false;
            for ($j = $i + 1; $j < $n; $j++) {
                $inner = trim($lines[$j]);
                if (str_contains($inner, $placeholder)) {
                    $foundSlot = true;
                }
                if (preg_match('/^\{%-?\s*endfor\s*-?%\}$/', $inner)) {
                    if ($foundSlot) {
                        return [$i, $j];
                    }
                    break;
                }
            }
        }

        for ($i = 0; $i < $n; $i++) {
            if (str_contains(trim($lines[$i]), $placeholder)) {
                return [$i, $i];
            }
        }
        return [null, null];
    }

    /**
     * Walk upward from the slot start line collecting the 1st and 2nd HTML
     * parent lines, skipping blank / non-HTML lines.
     *
     * @return array{int|null, int|null, string|null, string|null, int|null}
     */
    private static function findHtmlParents(array $lines, int $slotStart): array {
        $currentIndent  = strlen($lines[$slotStart]) - strlen(ltrim($lines[$slotStart], " \t"));
        $firstIdx       = null;
        $firstIndentLen = null;
        $firstIndentStr = null;
        $firstTagName   = null;
        $secondIdx      = null;

        for ($i = $slotStart - 1; $i >= 0; $i--) {
            $line    = $lines[$i];
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            $indentLen = strlen($line) - strlen(ltrim($line, " \t"));
            if ($indentLen >= $currentIndent) {
                continue;
            }
            if (!preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $trimmed, $m)) {
                continue;
            }

            if ($firstIdx === null) {
                $firstIdx       = $i;
                $firstIndentLen = $indentLen;
                $firstIndentStr = substr($line, 0, $indentLen);
                $firstTagName   = strtolower($m[1]);
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
