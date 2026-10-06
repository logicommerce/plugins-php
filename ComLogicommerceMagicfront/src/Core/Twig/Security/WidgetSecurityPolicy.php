<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Security;

use Twig\Markup;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Sandbox\SecurityNotAllowedMethodError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityPolicyInterface;

/**
 * What a widget template may use when it renders inside the Twig sandbox.
 *
 * A widget template is Twig written by whoever authored the widget, and it runs in the store (this plugin) and in the
 * preview renderer (docker). Without a sandbox, Twig lets a template reach PHP: `filter`, `map`, `sort` and `reduce`
 * accept a callable given as a string, so `['id']|map('system')` runs a shell command. The sandbox closes that — in
 * sandbox mode those four filters only take an arrow function — and this policy is the whitelist of everything
 * else.
 *
 * Where it applies: the preview renderer sandboxes everything it renders (it renders nothing but widgets); the store
 * sandboxes each widget's own template — `include(…, sandboxed = true)` in the core widgets macro, and
 * {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\WidgetTwigRenderingTrait} for a single widget —
 * so the theme's templates around it never pay for it.
 *
 * The lists come from the real catalog (every `widgets/<family>/v<N>.json`) plus the plugin's own core templates
 * (`twigCoreTemplates/`), which render inside the same sandbox because a widget calls them. Anything outside them is
 * refused with a SecurityError naming the tag, filter, function or method.
 *
 * - Functions: the plugin's own `mff_*` family (declared in PluginTwigBootstrap; any new one is allowed without
 *   touching this list) plus the few Twig core functions the catalog uses.
 * - Methods: only accessors (`get*`, `is*`, `has*`) and `__toString` — how `product.name` reaches a SDK object. No
 *   template calls an object method with arguments.
 * - Properties: allowed. Arrays are never checked, and a public property is data, not behaviour.
 *
 * `include()` is allowed because the core widgets macro renders each widget through it (`sandboxed = true`); what it
 * includes renders sandboxed too. Deliberately NOT allowed: `constant()` (it reads any PHP constant, and a store
 * defines secrets as constants), `source()`, `dump()`, and every tag outside the list (`sandbox`, `embed`,
 * `extends`, `use`, `block`…).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig\Security
 */
final class WidgetSecurityPolicy implements SecurityPolicyInterface {

    public const ALLOWED_TAGS = ['if', 'for', 'set', 'import', 'from', 'macro', 'include', 'apply'];

    public const ALLOWED_FILTERS = [
        'abs', 'batch', 'capitalize', 'column', 'date', 'default', 'e', 'escape', 'filter', 'first', 'format',
        'join', 'json_encode', 'keys', 'last', 'length', 'lower', 'map', 'merge', 'nl2br', 'number_format', 'raw',
        'reduce', 'replace', 'reverse', 'round', 'slice', 'sort', 'split', 'striptags', 'title', 'trim', 'upper',
        'url_encode',
    ];

    public const ALLOWED_FUNCTIONS = [
        'attribute', 'cycle', 'date', 'include', 'max', 'min', 'random', 'range', 'template_from_string',
        'addTimerDebugFlag',
    ];

    public const FUNCTION_PREFIX = 'mff_';

    public function checkSecurity($tags, $filters, $functions): void {
        foreach ($tags as $tag) {
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new SecurityNotAllowedTagError(sprintf('Tag "%s" is not allowed in a widget template.', $tag), $tag);
            }
        }
        foreach ($filters as $filter) {
            if (!in_array($filter, self::ALLOWED_FILTERS, true)) {
                throw new SecurityNotAllowedFilterError(sprintf('Filter "%s" is not allowed in a widget template.', $filter), $filter);
            }
        }
        foreach ($functions as $function) {
            if (!str_starts_with($function, self::FUNCTION_PREFIX) && !in_array($function, self::ALLOWED_FUNCTIONS, true)) {
                throw new SecurityNotAllowedFunctionError(sprintf('Function "%s" is not allowed in a widget template.', $function), $function);
            }
        }
    }

    public function checkMethodAllowed($obj, $method): void {
        if ($obj instanceof Markup) {
            return;
        }
        $name = strtolower((string) $method);
        if ($name === '__tostring' || preg_match('/^(get|is|has)[a-z0-9_]/', $name) === 1) {
            return;
        }
        throw new SecurityNotAllowedMethodError(
            sprintf('Calling "%s" method on a "%s" object is not allowed in a widget template.', $method, get_class($obj)),
            get_class($obj),
            $method
        );
    }

    public function checkPropertyAllowed($obj, $property): void {
    }
}
