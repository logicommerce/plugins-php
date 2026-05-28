<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Twig\Environment;

/**
 * THE single entry point for plugin Twig customisation. Both the storefront
 * fwk renderer (via GetWidgetHandler::buildTwigEnvironment + MagicfrontTrait)
 * and the docker template-renderer (via index.php) call this to make their
 * Twig environments behave identically for widget rendering.
 *
 * Pure orchestrator: it pairs the Class B globals defined on {@see ContextBuilder}
 * with the Class C functions held in {@see MagicfrontTwigFunctions}. No
 * function names or context keys live here.
 *
 * Renderer-specific compatibility shims (e.g. fwk's `addTimerDebugFlag` stub
 * that doesn't exist in the docker sandbox) stay in that renderer's own setup.
 * See README.md.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class PluginTwigBootstrap {

    /**
     * Apply globals + functions to an env that hasn't loaded any template yet
     * (i.e. its extension set is not initialised). Standard path for envs we
     * build ourselves or intercept before fwk's `loadCore()` runs.
     */
    public static function apply(Environment $twig, ContextBuilder $ctx): void {
        foreach ($ctx->toGlobals() as $key => $value) {
            $twig->addGlobal($key, $value);
        }
        MagicfrontTwigFunctions::addFunctions($twig, $ctx);
    }

    /**
     * Apply ONLY functions, via late-binding callback. Used for fwk's private
     * `$coreTwig` env which is already initialised by `loadCore()` before any
     * controller hook fires — at that point `addFunction()` and `addGlobal()`
     * throw `LogicException`. Globals can NOT be applied here; widget
     * templates rendered through coreTwig must not depend on plugin globals
     * (fwk-provided globals like `coreMode` come through fwk's own mechanism).
     */
    public static function applyLazyFunctions(Environment $twig, ContextBuilder $ctx): void {
        MagicfrontTwigFunctions::registerLateBinding($twig, $ctx);
    }
}
