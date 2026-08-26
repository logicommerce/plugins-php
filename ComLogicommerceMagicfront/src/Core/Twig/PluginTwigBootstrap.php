<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\MagicfrontTwigFunctions;
use Twig\Environment;

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
        foreach ($ctx->toGlobals() as $key => $value) {
            $twig->addGlobal($key, $value);
        }
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
