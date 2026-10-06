<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\MagicfrontTwigFunctions;
use Plugins\ComLogicommerceMagicfront\Core\Twig\Security\WidgetSecurityPolicy;
use Twig\Environment;
use Twig\Extension\SandboxExtension;

/**
 * Single entry point for plugin Twig customisation — both the storefront fwk
 * renderer and the docker template-renderer call this so their Twig envs
 * behave identically. Pairs Class B globals (ContextBuilder) with Class C
 * functions (MagicfrontTwigFunctions).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
class PluginTwigBootstrap {

    /** Apply globals + functions on an env not yet initialised (extension set unlocked). */
    public static function apply(Environment $twig, ContextBuilder $ctx): void {
        // The sandbox that widget templates render in (WidgetSecurityPolicy). Registered OFF: only the widget's own
        // template turns it on — include(…, sandboxed = true) in the widgets macro. Without the extension that
        // include would render UNsandboxed and say nothing, so it goes wherever the widget functions go. The
        // preview renderer registers its own, globally on, before calling this.
        if (!$twig->hasExtension(SandboxExtension::class)) {
            $twig->addExtension(new SandboxExtension(new WidgetSecurityPolicy(), false));
        }
        foreach ($ctx->toGlobals() as $key => $value) {
            $twig->addGlobal($key, $value);
        }
        // `mff_api` (#833): what a widget newer than the plugin asks for with `is defined`. FWK's core env, where the
        // store paints widgets, is locked by now: the widgets macro hands it to each widget instead.
        $twig->addGlobal(WidgetApi::VARIABLE, WidgetApi::forEnvironment($twig));
        MagicfrontTwigFunctions::addFunctions($twig, $ctx);
    }

    /**
     * Apply ONLY functions via late-binding — used for fwk's `$coreTwig` which
     * is already locked when controller hooks fire. Globals can NOT be applied.
     */
    public static function applyLazyFunctions(Environment $twig, ContextBuilder $ctx): void {
        MagicfrontTwigFunctions::registerLateBinding($twig, $ctx);
    }
}
