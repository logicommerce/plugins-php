<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Twig\Environment;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Holds every Twig function this plugin exposes to widget templates. Mirrors
 * fwk's {@see \FWK\Twig\Functions\TwigFunctionsCore} structure: one private
 * static factory per function, each carrying the function name inline.
 *
 * Adding a new function:
 *   1) write a private static factory returning a {@see TwigFunction}
 *   2) include it in {@see self::all()}
 *
 * Two registration modes coexist because fwk keeps a private `$coreTwig` env
 * that is already initialised (and therefore locked) by the time any
 * controller hook can reach it:
 *
 *   - {@see self::addFunctions()} — normal `$twig->addFunction(...)`. Use for
 *     envs we own or intercept before they parse any template (storefront
 *     main `$twig`, GetWidgetHandler's private env, docker template-renderer).
 *
 *   - {@see self::registerLateBinding()} — uses Twig's
 *     `registerUndefinedFunctionCallback`, the only API that ignores the init
 *     lock. Use for fwk's coreTwig.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class MagicfrontTwigFunctions {

    public static function addFunctions(Environment $twig, ContextBuilder $ctx): void {
        foreach (self::all($ctx) as $function) {
            $twig->addFunction($function);
        }
    }

    public static function registerLateBinding(Environment $twig, ContextBuilder $ctx): void {
        $byName = [];
        foreach (self::all($ctx) as $function) {
            $byName[$function->getName()] = $function;
        }
        $twig->registerUndefinedFunctionCallback(
            static fn(string $name): TwigFunction|false => $byName[$name] ?? false
        );
    }

    /** @return TwigFunction[] */
    private static function all(ContextBuilder $ctx): array {
        return [
            self::mffPrice($ctx),
            self::mffWidgetSlot(),
        ];
    }

    /**
     * `mff_price(value)` — formats a numeric price into the structured-span
     * HTML defined by {@see PriceFormatter}. The locale, currency code, and
     * optional symbol override come from the captured {@see ContextBuilder}.
     */
    private static function mffPrice(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_price',
            static fn(float $value): Markup => new Markup(PriceFormatter::format($value, $ctx), 'UTF-8'),
            ['is_safe' => ['html']]
        );
    }

    /**
     * `mff_widget_slot(arg)` — renders one slot child as a full widget block
     * (MFF marker comments + wrapper div + recursive child rendering).
     *
     * Replaces the former compile-time placeholder `{{ mff_widget_slot }}`.
     * Two call styles, one function:
     *
     *   {# bare / columns style — author iterates page.subpages #}
     *   <div mff-widget-slot-root="1">
     *     {% for subPage in page.subpages %}
     *       {{ mff_widget_slot(subPage) }}
     *     {% endfor %}
     *   </div>
     *
     *   {# parametric / fixed-slot style — string slotId lookup in page.subpages #}
     *   {{ mff_widget_slot('heading') }}
     *
     * String lookups always search `$context['page']->getSubpages()`. To resolve
     * inside a nested for-loop, use the explicit Page form instead.
     *
     * `needs_environment` + `needs_context` so the recursion into the standard
     * widget macro can borrow the caller's `version` and `widgetTemplateList`,
     * and so the empty-slot preview placeholder reads the right `previewMode`
     * flag.
     */
    private static function mffWidgetSlot(): TwigFunction {
        return new TwigFunction(
            'mff_widget_slot',
            static function (Environment $env, array $context, mixed $arg = null): Markup {
                // Parametric / fixed-slot style — `mff_widget_slot('id')`. The
                // matched subpage IS the widget to render; go through the
                // widget macro so canvas treats it as a real widget, not a
                // drop zone.
                if (is_string($arg)) {
                    $subPage = WidgetSlotRenderer::findSlotById($context, $arg);
                    if ($subPage === null) {
                        return new Markup('', 'UTF-8');
                    }
                    return new Markup(WidgetSlotRenderer::renderAsWidget($env, $context, $subPage), 'UTF-8');
                }
                // Bare / columns style — `mff_widget_slot(subPage)`. The
                // subpage IS a slot container; its own subpages render as
                // the actual widgets inside. Accepts either a Page object
                // (storefront fwk path) or an array (docker template-renderer
                // path) because WidgetArrayTransformer keeps data as arrays
                // to stay FWK/SDK-free.
                if (is_array($arg) || is_object($arg)) {
                    return new Markup(WidgetSlotRenderer::renderSlotContainer($env, $context, $arg), 'UTF-8');
                }
                return new Markup('', 'UTF-8');
            },
            ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]
        );
    }
}
