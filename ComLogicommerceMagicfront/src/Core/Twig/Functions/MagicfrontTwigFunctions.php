<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Functions;

use FWK\Core\Resources\Session;
use Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder;
use Twig\Environment;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * Twig functions exposed to widget templates. Mirrors fwk's
 * {@see \FWK\Twig\Functions\TwigFunctionsCore} structure: one private static
 * factory per function. New function = factory + list in {@see self::all()}.
 *
 * `registerLateBinding` exists because fwk's `$coreTwig` is locked by the time
 * controller hooks fire; `registerUndefinedFunctionCallback` is the only API
 * that bypasses the init lock.
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
            self::mffGetLanguages($ctx),
            self::mffGetMoney($ctx),
            self::mffGetCategories($ctx),
            self::mffWidgetSlot(),
            self::mffGetAccount(),
            self::mffPreviewMode($ctx),
        ];
    }

    /** `mff_previewMode()` → true inside the editor canvas iframe / mfToken preview. Exposed as a
     *  FUNCTION (not a global) because fwk's `$coreTwig` — the env that renders widgets — is locked
     *  by the time hooks fire and cannot take globals; functions bind lazily. Widgets use it to
     *  render mock/demo data only in preview, never on the live storefront. */
    private static function mffPreviewMode(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_previewMode',
            static fn(): bool => $ctx->previewMode,
        );
    }

    /** `mff_getAccount()` → the storefront session account for the accountPanel logged state.
     *  Anonymous (isLogged false) when no registered session. */
    private static function mffGetAccount(): TwigFunction {
        return new TwigFunction(
            'mff_getAccount',
            static function (): array {
                $anonymous = ['isLogged' => false, 'nick' => '', 'name' => '', 'image' => ''];
                if (!class_exists(Session::class)) {
                    return $anonymous;
                }
                $session = Session::getInstance();
                if (!$session->isLogged()) {
                    return $anonymous;
                }
                $user = $session->getUser();
                return [
                    'isLogged' => true,
                    'nick' => $user->getNick(),
                    'name' => $user->getNick(),
                    'image' => $user->getImage(),
                ];
            },
        );
    }


    /** `mff_getCategories()` → ContextBuilder's top-categories tree (each with one level of
     *  subcategories). Consumer: the header categoryMenu widget. Empty array when unresolvable
     *  (docker preview / no catalog) → the widget renders its empty-state placeholder. */
    private static function mffGetCategories(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_getCategories',
            static fn(): array => $ctx->categories,
        );
    }

    /** `mff_getLanguages()` → ContextBuilder's `languages` array. Empty when unresolvable. */
    private static function mffGetLanguages(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_getLanguages',
            static fn(): array => $ctx->languages,
        );
    }

    /** `mff_getMoney()` → ContextBuilder's `currencies` array. Empty when unresolvable. */
    private static function mffGetMoney(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_getMoney',
            static fn(): array => $ctx->currencies,
        );
    }

    /**
     * `mff_price(value)` — storefront delegates to fwk's `outputHtmlCurrency` so
     * we never drift from fwk's locale/currency logic; docker preview has no
     * fwk function → bare digits with `¤`.
     */
    private static function mffPrice(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_price',
            static function (Environment $env, float $value): Markup {
                $fwk = $env->getFunction('outputHtmlCurrency');
                if ($fwk instanceof TwigFunction) {
                    $out = $fwk->getCallable()($value);
                    return $out instanceof Markup ? $out : new Markup((string) $out, 'UTF-8');
                }
                return new Markup(self::previewPrice($value), 'UTF-8');
            },
            ['needs_environment' => true, 'is_safe' => ['html']]
        );
    }

    /** Docker preview fallback — no fwk `outputHtmlCurrency`, no real currency. */
    private static function previewPrice(float $value): string {
        $display = number_format($value, 2, ',', '');
        [$int, $dec] = array_pad(explode(',', $display, 2), 2, '');
        return '<span class="price">'
            . '<span class="integerPrice" content="' . number_format($value, 2, '.', '') . '">' . $int . '</span>'
            . ($dec !== '' ? '<span class="decimalPrice">,' . $dec . '</span>' : '')
            . '<span class="currencySymbol">¤</span>'
            . '</span>';
    }

    /**
     * `mff_widget_slot(arg)` — renders one slot child as a widget block.
     * String arg → lookup by slotId in `page.subpages`; Page/array arg →
     * render as slot container. Needs env+context for recursion into the
     * widget macro.
     */
    private static function mffWidgetSlot(): TwigFunction {
        return new TwigFunction(
            'mff_widget_slot',
            static function (Environment $env, array $context, mixed $arg = null): Markup {
                if (is_string($arg)) {
                    $subPage = WidgetSlotRenderer::findSlotById($context, $arg);
                    if ($subPage === null) {
                        return new Markup('', 'UTF-8');
                    }
                    return new Markup(WidgetSlotRenderer::renderAsWidget($env, $context, $subPage), 'UTF-8');
                }
                if (is_array($arg) || is_object($arg)) {
                    return new Markup(WidgetSlotRenderer::renderSlotContainer($env, $context, $arg), 'UTF-8');
                }
                return new Markup('', 'UTF-8');
            },
            ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]
        );
    }
}
