<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig;

use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\MagicfrontProductFunctions;
use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\MagicfrontTwigFunctions;
use Plugins\ComLogicommerceMagicfront\Core\Twig\Functions\WidgetSlotRenderer;
use Twig\Environment;
use Twig\Markup;

/**
 * What a widget template asks the plugin for when it is NEWER than the plugin in some store: `mff_api`, a variable of
 * every widget's context (#833). A template can ask `{% if mff_api is defined %}` and, when an older plugin does not
 * have it, paint what it painted before. A new Twig FUNCTION cannot be asked that way: an unknown function stops the
 * template from compiling and the widget paints nothing — which is why widgets already in stores never call one
 * (WidgetPluginFunctionsGuardTest).
 *
 * Accessors only (`get*`): the sandbox lets a template call them on the plugin's own objects (WidgetSecurityPolicy),
 * and Twig finds them from the short name — `mff_api.label('ORDER_BY', 'Ordenar por')` calls {@see getLabel}. Each one
 * is the same code as the function of the same job, so the two can never answer differently.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig
 */
final class WidgetApi {

    /** The context key the widget templates read. */
    public const VARIABLE = 'mff_api';

    private static ?self $instance = null;

    /** @var \WeakMap<Environment, self>|null one object per Twig environment that paints widgets */
    private static ?\WeakMap $byEnvironment = null;

    /** The environment painting the widget: a slot is rendered by the widgets macro of THAT environment. */
    private ?Environment $env = null;

    /** Without an environment: everything but {@see getWidgetSlotFor}, which then paints nothing. */
    public static function instance(): self {
        return self::$instance ??= new self();
    }

    /** The object for the widgets painted by `$env` — what every caller that has the environment hands out. */
    public static function forEnvironment(Environment $env): self {
        self::$byEnvironment ??= new \WeakMap();
        if (!isset(self::$byEnvironment[$env])) {
            $api = new self();
            $api->env = $env;
            self::$byEnvironment[$env] = $api;
        }
        return self::$byEnvironment[$env];
    }

    /** `mff_api.label(key, fallback)` = `mff_label(key, fallback)`: the storefront label in the shopper's language. */
    public function getLabel(string $key, string $fallback = ''): string {
        return MagicfrontTwigFunctions::label($key, $fallback);
    }

    /** `mff_api.productPrices(product)` = `mff_product_prices(product)`: the price the shop charges, or null. */
    public function getProductPrices(mixed $product): ?array {
        return MagicfrontProductFunctions::prices($product);
    }

    /** `mff_api.productStock(product)` = `mff_product_stock(product)`: the stock the shop shows, or null. */
    public function getProductStock(mixed $product): ?array {
        return MagicfrontProductFunctions::stock($product);
    }

    /** `mff_api.productCombinations(product)`: per combination id, the price it charges and whether it can be bought. */
    public function getProductCombinations(mixed $product): array {
        return MagicfrontProductFunctions::combinationRows($product);
    }

    /** `mff_api.editorNote(key)` = `mff_editor_note(key)`: a notice for the merchant in the editor, never the shop. */
    public function getEditorNote(string $key): string {
        return MagicfrontTwigFunctions::editorNote($key);
    }

    /**
     * `mff_api.widgetSlotFor(_context, slotId, product, index)` = `mff_widget_slot_for(slotId, product, index)`: the LIST
     * slot `slotId` painted once (index 0 the editable one, the others marked copies). A function gets the template's
     * context from Twig; a method does not, so the template hands it over (`_context`).
     */
    public function getWidgetSlotFor(array $context, string $slotId, mixed $product = null, int $index = 0): Markup {
        if ($this->env === null) {
            return new Markup('', 'UTF-8');
        }
        return new Markup(WidgetSlotRenderer::renderRepeated($this->env, $context, $slotId, $product, $index), 'UTF-8');
    }
}
