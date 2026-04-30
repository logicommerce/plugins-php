<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits;

use Plugins\ComLogicommerceMagicfront\Core\Resources\WidgetTypeCollector;
use Plugins\ComLogicommerceMagicfront\Core\Services\StyleMapper;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplate;
use Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetTemplateStyle;

/**
 * CSS generation logic for widget templates and instance style values.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits
 */
trait CssGeneratorTrait {

    /** Media query for the tablet breakpoint. Must match preview's InstanceCssBuilder. */
    private const TABLET_MEDIA = '@media (max-width: 991px)';

    /** Media query for the mobile breakpoint. Must match preview's InstanceCssBuilder. */
    private const MOBILE_MEDIA = '@media (max-width: 767px)';

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Build final CSS from widget templates and instance style overrides.
     *
     * @param WidgetInstance[]  $widgets   Flattened widget list (for per-instance CSS).
     * @param WidgetTemplate[]  $templates Templates indexed by type.
     */
    protected function generateCss(array $widgets, array $templates): string {
        $styleElementMap = $this->buildStyleElementMap($templates);
        $cssPropertyMap  = $this->buildStyleCssPropertyMap($templates);
        $slotTypes       = $this->collectSlotTypes($templates);
        $templateCss     = $this->mergeTemplateCss($templates);
        $instanceCss     = $this->generateInstanceCss($widgets, $styleElementMap, $cssPropertyMap, $slotTypes);

        if ($templateCss === '') {
            return $instanceCss;
        }
        if ($instanceCss === '') {
            return $templateCss;
        }
        return $templateCss . "\n\n" . $instanceCss;
    }

    /**
     * Widget types whose template declares **either** a `childStructure` OR a
     * non-empty typed `slots[]`. Both are slot container mechanisms — their
     * instance CSS needs `:not()` to stop descendant leakage at the slot
     * boundary. Leaf widgets without either get the simpler selector with no
     * `:not()`.
     *
     * Fixed 2026-04-21: previously only `childStructure` was checked, so any
     * widget using the newer `slots[]` composition (e.g. `imageTextSplit`
     * after its 2026-04 migration, or `imageTextSplitSlots`) leaked the
     * parent's instance CSS into every slot-child widget root — observed as
     * a 48px padding on a button inside an imageTextSplit.
     *
     * @param  WidgetTemplate[] $templates
     * @return array<string, true>
     */
    private function collectSlotTypes(array $templates): array {
        $slotTypes = [];
        foreach ($templates as $type => $template) {
            $hasChildStructure = $template->getChildStructure() !== null;
            $hasSlots = !empty($template->getSlots());
            if ($hasChildStructure || $hasSlots) {
                $slotTypes[(string) $type] = true;
            }
        }
        return $slotTypes;
    }

    // ─── Template CSS ─────────────────────────────────────────────────────────

    /**
     * @param WidgetTemplate[] $templates
     */
    private function mergeTemplateCss(array $templates): string {
        return implode("\n", array_map(
            static fn (WidgetTemplate $t): string => $t->getTemplateCss(),
            $templates
        ));
    }

    // ─── Instance CSS ─────────────────────────────────────────────────────────

    /**
     * @param WidgetInstance[]      $widgets
     * @param array<string,string>  $cssPropertyMap  styleId → cssProperty across all templates.
     * @param array<string,true>    $slotTypes       Types whose template has a `childStructure`.
     */
    private function generateInstanceCss(array $widgets, array $styleElementMap, array $cssPropertyMap, array $slotTypes): string {
        // Desktop canonical rules always emit. Tablet / mobile blocks emit only
        // when at least one styleValue carries a matching sibling override;
        // templates with no responsive declarations pay zero cost. Matches
        // preview's InstanceCssBuilder::build contract (kept in sync per
        // template-renderers.md §5).
        $desktopBlocks = [];
        $tabletBlocks  = [];
        $mobileBlocks  = [];
        foreach ($widgets as $widget) {
            $desktopBlocks = array_merge($desktopBlocks, $this->instanceCssFor($widget, $styleElementMap, $cssPropertyMap, $slotTypes, null));
            $tabletBlocks  = array_merge($tabletBlocks,  $this->instanceCssFor($widget, $styleElementMap, $cssPropertyMap, $slotTypes, 'tablet'));
            $mobileBlocks  = array_merge($mobileBlocks,  $this->instanceCssFor($widget, $styleElementMap, $cssPropertyMap, $slotTypes, 'mobile'));
        }

        $out = implode("\n", $desktopBlocks);
        if ($tabletBlocks !== []) {
            $out .= ($out === '' ? '' : "\n") . self::TABLET_MEDIA . " {\n" . $this->indent(implode("\n", $tabletBlocks)) . "}\n";
        }
        if ($mobileBlocks !== []) {
            $out .= ($out === '' ? '' : "\n") . self::MOBILE_MEDIA . " {\n" . $this->indent(implode("\n", $mobileBlocks)) . "}\n";
        }
        return $out;
    }

    /**
     * @param  string|null $breakpoint null = desktop canonical; 'tablet'/'mobile' = sibling override projection.
     * @return string[] CSS blocks for a single widget (one per element-id × descendantSelector group).
     */
    private function instanceCssFor(WidgetInstance $widget, array $styleElementMap, array $cssPropertyMap, array $slotTypes, ?string $breakpoint = null): array {
        $type        = $widget->getType();
        $typeMap     = $styleElementMap[$type] ?? [];
        $styleValues = $this->resolveStyleValues($widget, $typeMap);
        if ($styleValues === []) {
            return [];
        }

        if ($breakpoint !== null) {
            $styleValues = $this->extractBreakpoint($styleValues, $breakpoint);
            if ($styleValues === []) {
                return [];
            }
        }

        [$scopeId, $childIndex, $hasSlot] = $this->resolveWidgetScope($widget, $slotTypes);

        $blocks = [];
        foreach ($this->groupStylesByElementAndDescendant($styleValues) as $group) {
            $declarations = StyleMapper::generateCssDeclarations($group['styles'], $group['elementId'], $cssPropertyMap);
            if ($declarations === []) {
                continue;
            }
            $selector = $this->buildCssSelector($scopeId, $group['elementId'], $childIndex, $hasSlot, $group['descendantSelector']);
            $blocks[] = $this->renderCssBlock($selector, $declarations);
        }
        return $blocks;
    }

    /**
     * Project styleValues to a given breakpoint. Returns only entries carrying a
     * non-null `value` inside the `tablet` / `mobile` sibling; `unit` falls back
     * to the parent's unit so authors only need `"tablet": {"value": 1.5}` for
     * homogeneous-unit cases (see contract.md §4b).
     *
     * Kept behaviour-identical to preview's
     * `InstanceCssBuilder::extractBreakpoint` so both renderers emit the same
     * media-query CSS for the same DTO.
     *
     * @return array<int, array<string,mixed>>
     */
    private function extractBreakpoint(array $styleValues, string $breakpoint): array {
        $out = [];
        foreach ($styleValues as $style) {
            if (!is_array($style) && !is_object($style)) {
                continue;
            }
            $styleArr = is_array($style) ? $style : get_object_vars($style);
            $override = $styleArr[$breakpoint] ?? null;
            if (is_object($override)) {
                $override = get_object_vars($override);
            }
            if (!is_array($override) || !array_key_exists('value', $override) || $override['value'] === null) {
                continue;
            }
            $out[] = [
                'styleId'     => $styleArr['styleId']     ?? null,
                'styleTagPId' => $styleArr['styleTagPId'] ?? ($styleArr['styleId'] ?? null),
                'elementId'   => $styleArr['elementId']   ?? '',
                'value'       => $override['value'],
                'unit'        => $override['unit']        ?? ($styleArr['unit'] ?? ''),
            ];
        }
        return $out;
    }

    private function indent(string $block): string {
        $lines  = explode("\n", rtrim($block, "\n"));
        $prefix = '  ';
        return implode("\n", array_map(
            static fn (string $l): string => $l === '' ? $l : $prefix . $l,
            $lines
        )) . "\n";
    }

    /**
     * Pick the widget's styleValues, falling back to a legacy propertyValues mapping
     * so older records without a `styleValues` field still render.
     */
    private function resolveStyleValues(WidgetInstance $widget, array $typeMap): array {
        $styleValues = $widget->getStyleValues();
        return $styleValues !== []
            ? $this->applyElementIdMapping($styleValues, $typeMap)
            : $this->propertyValuesToStyleValues($widget->getPropertyValues(), $typeMap);
    }

    /**
     * Resolve the scope used to emit CSS for a widget.
     *
     * "Slot-template" widgets (column items etc.) live as slots inside their
     * parent's template — they have no `.mff-widget` wrapper of their own, so
     * their instance CSS must be scoped under the parent's widgetId plus the
     * `data-mff-child-index` attribute injected by the template transformer.
     * The API signals these by leaving `type` empty; older records used a
     * childStructure UUID instead — both are treated as slot-templates here.
     *
     * Real widgets (countdownTimer, heading, ...) — including those placed
     * inside a column slot — always render their own `.mff-widget` wrapper,
     * so their own widgetId is a sufficient scope.
     *
     * @param  array<string,true> $slotTypes
     * @return array{0:string, 1:?int, 2:bool}  [scopeWidgetId, childIndex, hasSlot]
     */
    private function resolveWidgetScope(WidgetInstance $widget, array $slotTypes): array {
        $widgetId       = $widget->getId();
        $parentId       = $widget->getParentId();
        $type           = $widget->getType();
        $isSlotTemplate = $type === '' || WidgetTypeCollector::isChildStructureUuid($type);
        $hasParent      = $parentId !== null && $parentId !== '';

        $scopeId    = ($isSlotTemplate && $hasParent) ? $parentId : $widgetId;
        $childIndex = ($isSlotTemplate && $hasParent) ? $widget->getOrderIndex() : null;
        // Slot-templates inherit slot status from their parent (which is always a
        // container). Real widgets are slot containers only when their own
        // template declares childStructure.
        $hasSlot    = $isSlotTemplate || isset($slotTypes[$type]);

        return [$scopeId, $childIndex, $hasSlot];
    }

    /**
     * Group style-value entries by their elementId. Entries without an
     * elementId are dropped (they cannot be scoped to an element selector).
     *
     * @return array<string, array<int, array>>
     */
    private function groupStylesByElement(array $styleValues): array {
        $byElement = [];
        foreach ($styleValues as $style) {
            $elementId = $style['elementId'] ?? '';
            if ($elementId !== '') {
                $byElement[$elementId][] = $style;
            }
        }
        return $byElement;
    }

    /**
     * Group by the (elementId, descendantSelector) composite so styles that
     * declare different descendants land in separate rule blocks. The
     * descendantSelector comes from the styleValue itself — it's written
     * onto each instance by StyleValueArranger at seed / upsert time. Matches
     * preview's InstanceCssBuilder::renderForBreakpoint grouping.
     *
     * @return array<string, array{elementId:string, descendantSelector:string, styles:array}>
     */
    private function groupStylesByElementAndDescendant(array $styleValues): array {
        $groups = [];
        foreach ($styleValues as $style) {
            $elementId = $style['elementId'] ?? '';
            if ($elementId === '') {
                continue;
            }
            $descendantSelector = isset($style['descendantSelector']) && is_string($style['descendantSelector'])
                ? $style['descendantSelector']
                : '';
            $key = $elementId . "\0" . $descendantSelector;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'elementId' => $elementId,
                    'descendantSelector' => $descendantSelector,
                    'styles' => [],
                ];
            }
            $groups[$key]['styles'][] = $style;
        }
        return $groups;
    }

    /**
     * Render a single `selector { ... }` block. Direct CSS properties are
     * emitted before CSS custom properties so the declaration order mirrors
     * what authors expect when reading the output.
     *
     * @param array<string,string> $declarations
     */
    private function renderCssBlock(string $selector, array $declarations): string {
        $direct = [];
        $custom = [];
        foreach ($declarations as $prop => $value) {
            if (str_starts_with($prop, '--')) {
                $custom[] = "  {$prop}: {$value};";
            } else {
                $direct[] = "  {$prop}: {$value};";
            }
        }
        return $selector . " {\n" . implode("\n", array_merge($direct, $custom)) . "\n}\n";
    }

    // ─── Style element map ────────────────────────────────────────────────────

    /**
     * Build a propertyId → elementId map for each widget type (and child structure).
     * Used to resolve missing elementId fields on style values.
     *
     * @param  WidgetTemplate[] $templates
     * @return array<string, array<string, string>> type => [propertyId => elementId]
     */
    private function buildStyleElementMap(array $templates): array {
        $map = [];
        foreach ($templates as $type => $template) {
            $styleMap = $this->parseStyleDefinitions($template->getStyles());
            if ($styleMap !== []) {
                $map[(string) $type] = $styleMap;
            }

            // Child structure types (UUIDs) are tracked separately so column
            // children can resolve their elementId even when it is absent from styleValues.
            $childStructure = $template->getChildStructure();
            if ($childStructure === null) {
                continue;
            }
            $childId  = $childStructure->getId();
            $childMap = $this->parseStyleDefinitions($childStructure->getStyles());
            if ($childId !== '' && $childMap !== []) {
                $map[$childId] = $childMap;
            }
        }
        return $map;
    }

    /**
     * Parse template style definitions into a styleId → elementId map.
     *
     * @param  WidgetTemplateStyle[] $styles
     * @return array<string, string>
     */
    private function parseStyleDefinitions(array $styles): array {
        $map = [];
        foreach ($styles as $style) {
            $styleId   = $style->getId();
            $elementId = $style->getElementId();
            if ($styleId !== '' && $elementId !== '') {
                $map[$styleId] = $elementId;
            }
        }
        return $map;
    }

    /**
     * Build a global styleId → cssProperty map from every template's style definitions
     * (parent + childStructure). styleIds are unique per template scope; StyleMapper
     * uses this map to emit the real CSS property declared by the template, falling
     * back to the styleId when a style is missing from the map so legacy widgets with
     * styleId === cssProperty continue to render unchanged.
     *
     * @param  WidgetTemplate[] $templates
     * @return array<string, string>
     */
    private function buildStyleCssPropertyMap(array $templates): array {
        $map = [];
        foreach ($templates as $template) {
            $this->collectCssProperties($template->getStyles(), $map);
            $childStructure = $template->getChildStructure();
            if ($childStructure !== null) {
                $this->collectCssProperties($childStructure->getStyles(), $map);
            }
        }
        return $map;
    }

    /**
     * @param WidgetTemplateStyle[]  $styles
     * @param array<string, string> &$target
     */
    private function collectCssProperties(array $styles, array &$target): void {
        foreach ($styles as $style) {
            $styleId     = $style->getId();
            $cssProperty = $style->getCssProperty();
            if ($styleId !== '' && $cssProperty !== '') {
                $target[$styleId] = $cssProperty;
            }
        }
    }

    // ─── Style value helpers ──────────────────────────────────────────────────

    /**
     * Convert legacy propertyValues to the styleValues format expected by StyleMapper.
     */
    private function propertyValuesToStyleValues(array $propertyValues, array $typeMap): array {
        $styleValues = [];
        foreach ($propertyValues as $pv) {
            $propId = $pv['propertyId'] ?? '';
            if ($propId === '' || ($pv['enabled'] ?? true) === false) {
                continue;
            }
            $value = $pv['value'] ?? '';
            if ($value === '') {
                continue;
            }
            $elementId = $pv['elementId'] ?? '';
            if ($elementId === '' && isset($typeMap[$propId])) {
                $elementId = $typeMap[$propId];
            }
            if ($elementId === '') {
                continue;
            }
            $styleValues[] = [
                'styleTagPId' => $propId,
                'value'       => $value,
                'elementId'   => $elementId,
                'unit'        => $pv['unit'] ?? '',
            ];
        }
        return $styleValues;
    }

    /**
     * Fill in missing elementId fields on style values using the type's property map.
     */
    private function applyElementIdMapping(array $styleValues, array $typeMap): array {
        if ($typeMap === []) {
            return $styleValues;
        }
        foreach ($styleValues as $i => $style) {
            if (($style['elementId'] ?? '') !== '') {
                continue;
            }
            $propId = $style['styleTagPId'] ?? $style['propertyId'] ?? '';
            if ($propId !== '' && isset($typeMap[$propId])) {
                $styleValues[$i]['elementId'] = $typeMap[$propId];
            }
        }
        return $styleValues;
    }

    // ─── Selector ─────────────────────────────────────────────────────────────

    /**
     * Build the CSS selector used to scope instance style values. The `:not()`
     * clause is only emitted for slot containers so descendant slot-item widgets
     * do not inherit the container's styles through the CSS cascade. When a
     * {@code descendantSelector} fragment is provided it is appended verbatim
     * after the scoped target element — enables styling of merchant-authored
     * descendants (e.g. links inside a rich-text block) without requiring
     * {@code data-mff-el} on elements the merchant types themselves. The
     * fragment is validated server-side by CssSelectorValidator so it cannot
     * break out of the rule.
     */
    private function buildCssSelector(string $widgetId, string $elementId, ?int $childIndex = null, bool $hasSlot = false, string $descendantSelector = ''): string {
        $escapedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $widgetId);
        $base      = ".mff-widget[data-widget-id=\"{$escapedId}\"]";

        if ($childIndex !== null) {
            $base .= " [data-mff-child-index=\"{$childIndex}\"]";
        }

        if ($elementId === '') {
            return $base . $descendantSelector;
        }

        $escapedEl = preg_replace('/[^a-zA-Z0-9_-]/', '', $elementId);
        $target    = "{$base} [data-mff-el=\"{$escapedEl}\"]";

        if (!$hasSlot) {
            return $target . $descendantSelector;
        }
        return "{$target}{$descendantSelector}:not({$base} [data-mff-widget-root] [data-mff-el=\"{$escapedEl}\"]{$descendantSelector})";
    }
}
