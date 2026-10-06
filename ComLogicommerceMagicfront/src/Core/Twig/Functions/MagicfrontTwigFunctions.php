<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Functions;

use FWK\Core\Resources\Language;
use FWK\Core\Resources\Session;
use FWK\Enums\LanguageLabels;
use Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder;
use Plugins\ComLogicommerceMagicfront\Core\Twig\WidgetApi;
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
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Twig\Functions
 */
class MagicfrontTwigFunctions {

    private const EDITOR_NOTE_DEFAULT_LANGUAGE = 'en';

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
            ...MagicfrontProductFunctions::all($ctx),
            self::mffPrice($ctx),
            self::mffGetLanguages($ctx),
            self::mffGetMoney($ctx),
            self::mffGetCategories($ctx),
            self::mffWidgetSlot(),
            self::mffWidgetSlotFor(),
            self::mffRepeatIndex(),
            self::mffGetAccount(),
            self::mffPreviewMode($ctx),
            self::mffIsCanvasMode($ctx),
            self::mffLabel(),
            self::mffWidgetApi(),
            self::mffEditorNote(),
        ];
    }

    /** `mff_editor_note('sizeGuideWindow')` → a notice the EDITOR shows the merchant (never the shop), in the language
     *  being edited (the session's; English where there is no session, as in the docker renderer). Editor notices are
     *  not content: a property with an English seed showed the merchant developer text in the wrong language. */
    private static function mffEditorNote(): TwigFunction {
        return new TwigFunction('mff_editor_note', static fn(string $key): string => self::editorNote($key));
    }

    /** The editor notices by key, in es / ca / en; {@see editorNote} picks the language. */
    private const EDITOR_NOTES = [
        'sizeGuideWindow' => [
            'es' => 'Ventana de la guía de tallas (solo en el editor): haz clic para abrirla y editar su contenido.',
            'ca' => "Finestra de la guia de talles (només a l'editor): fes clic per obrir-la i editar-ne el contingut.",
            'en' => 'Size guide window (editor only): click to open it and edit its content.',
        ],
        // The stock alert of a product sheet while it does not show in the shop (#1148): the merchant sees it dimmed to
        // style it, and without a word it looked like a broken form.
        'stockAlertHidden' => [
            'es' => 'Aviso de «sin stock» (solo en el editor): en la tienda aparece cuando la opción elegida está agotada. Ahora mismo no se ve.',
            'ca' => "Avís de «sense estoc» (només a l'editor): a la botiga apareix quan l'opció triada està exhaurida. Ara mateix no es veu.",
            'en' => 'Out-of-stock notice (editor only): the shop shows it when the chosen option is sold out. It is hidden right now.',
        ],
        'stockAlertsDisabled' => [
            'es' => 'Aviso de «sin stock» (solo en el editor): tu tienda no tiene activos los avisos de stock, así que nunca se ve.',
            'ca' => "Avís de «sense estoc» (només a l'editor): la teva botiga no té actius els avisos d'estoc, així que no es veu mai.",
            'en' => 'Out-of-stock notice (editor only): stock alerts are turned off in your shop, so it never shows.',
        ],
    ];

    /** The editor notice `$key` in the language being edited, '' for an unknown key. */
    public static function editorNote(string $key): string {
        if (!isset(self::EDITOR_NOTES[$key])) {
            return '';
        }
        $language = self::EDITOR_NOTE_DEFAULT_LANGUAGE;
        try {
            if (class_exists(Session::class)) {
                $code = Session::getInstance()->getGeneralSettings()->getLanguage();
                if (is_string($code) && $code !== '') {
                    $language = strtolower(substr($code, 0, 2));
                }
            }
        } catch (\Throwable) {
            // No session: the neutral language.
        }
        return self::EDITOR_NOTES[$key][$language] ?? self::EDITOR_NOTES[$key][self::EDITOR_NOTE_DEFAULT_LANGUAGE];
    }

    /** `mff_label('ORDER_BY', 'Ordenar por')` → the storefront label named by the FWK LanguageLabels
     *  constant, in the shopper's language (the languageSheet the request already loaded, SITE
     *  overrides included). `fallback` where there is no FWK (the editor's docker renderer) or the key
     *  is unknown / empty. FWK keys only: a SITE key exists only in the commerce repos that declare it.
     *  THE way a widget prints fixed UI text (sort names, filter actions, "Results") that the shopper
     *  must read in their language — a merchant-editable text stays a LOCALIZED property. */
    private static function mffLabel(): TwigFunction {
        return new TwigFunction(
            'mff_label',
            static fn(string $key, string $fallback = ''): string => self::label($key, $fallback),
        );
    }

    /** `mff_widget_api()` → the `mff_api` object the widgets macro hands every widget. The plugin's own macro
     *  calls it, never a widget template: a widget reads the variable, asking `mff_api is defined` first. */
    private static function mffWidgetApi(): TwigFunction {
        return new TwigFunction('mff_widget_api', static fn(Environment $env): WidgetApi => WidgetApi::forEnvironment($env), ['needs_environment' => true]);
    }

    /** The label `mff_label` and `mff_api.label` print (one code for both, see WidgetApi). */
    public static function label(string $key, string $fallback = ''): string {
        if (!class_exists(Language::class) || !class_exists(LanguageLabels::class)) {
            return $fallback;
        }
        $constant = LanguageLabels::class . '::' . $key;
        if (!defined($constant)) {
            return $fallback;
        }
        $value = Language::getInstance()->getLabelValue((string) constant($constant), '');
        return $value !== '' ? $value : $fallback;
    }

    /** `mff_isCanvasMode()` → true ONLY inside the editor canvas (iframe + token). THE mock switch:
     *  widgets render demo data here and nowhere else. Exposed as a FUNCTION (not a global) because
     *  fwk's `$coreTwig` — the env that renders widgets — is locked by the time hooks fire and cannot
     *  take globals; functions bind lazily. */
    private static function mffIsCanvasMode(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_isCanvasMode',
            static fn(): bool => $ctx->canvasMode,
        );
    }

    /** `mff_previewMode()` → the canvas OR the standalone preview tab. Superseded by
     *  `mff_isCanvasMode()` and unused by current widgets, but KEPT FOREVER: already-published page
     *  blobs carry templates that call it, and removing it would fail their Twig compile. */
    private static function mffPreviewMode(ContextBuilder $ctx): TwigFunction {
        return new TwigFunction(
            'mff_previewMode',
            static fn(): bool => $ctx->previewMode,
        );
    }

    /** `mff_getAccount()` → minimal session login state for the accountPage state switch.
     *  Anonymous (isLogged false) when no registered session. Full account DATA is attached
     *  per-route to `page.account` (product/category pattern), not exposed here. */
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
            static function (Environment $env, float $value) use ($ctx): Markup {
                $fwk = $env->getFunction('outputHtmlCurrency');
                if ($fwk instanceof TwigFunction) {
                    $out = $fwk->getCallable()($value);
                    return $out instanceof Markup ? $out : new Markup((string) $out, 'UTF-8');
                }
                return new Markup(self::previewPrice($value, self::activeCurrencySymbol($ctx)), 'UTF-8');
            },
            ['needs_environment' => true, 'is_safe' => ['html']]
        );
    }

    /**
     * The active currency's symbol from the context (the docker renderer builds it from the request's currencyCode,
     * EUR by default), or `¤` when the context has none.
     */
    private static function activeCurrencySymbol(ContextBuilder $ctx): string {
        foreach ($ctx->currencies as $currency) {
            if (!empty($currency['isActive']) && is_string($currency['symbol'] ?? null) && $currency['symbol'] !== '') {
                return $currency['symbol'];
            }
        }
        return '¤';
    }

    /** Docker preview fallback — no fwk `outputHtmlCurrency`: the context's active currency symbol. */
    private static function previewPrice(float $value, string $symbol): string {
        $display = number_format($value, 2, ',', '');
        [$int, $dec] = array_pad(explode(',', $display, 2), 2, '');
        return '<span class="price">'
            . '<span class="integerPrice" content="' . number_format($value, 2, '.', '') . '">' . $int . '</span>'
            . ($dec !== '' ? '<span class="decimalPrice">,' . $dec . '</span>' : '')
            . '<span class="currencySymbol">' . htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</span>';
    }

    /**
     * `mff_widget_slot(arg, scope=null)` — renders one slot child as a widget block.
     * String arg → lookup by slotId; Page/array arg → render as slot container. When
     * `scope` (a Page) is given with a string arg, the slotId is resolved inside `scope`'s
     * subpages instead of the template's `page` — this is how a childStructure pseudo's
     * own `childStructure.slots[]` child is rendered from within the parent's
     * `{% for subpage in page.subpages %}` loop (the pseudo is never rendered on its own,
     * so its typed slot child is unreachable via the default `page`-scoped lookup).
     * Needs env+context for recursion into the widget macro.
     */
    private static function mffWidgetSlot(): TwigFunction {
        return new TwigFunction(
            'mff_widget_slot',
            static function (Environment $env, array $context, mixed $arg = null, mixed $scope = null): Markup {
                if (is_string($arg)) {
                    $subPage = $scope !== null
                        ? WidgetSlotRenderer::findSlotById(['page' => $scope], $arg)
                        : WidgetSlotRenderer::findSlotById($context, $arg);
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

    /**
     * `mff_widget_slot_for(slotId, product, index)` — paints the LIST slot `slotId` once for `product`: the
     * composed product card of a listing (`{% for product in products %}…{{ mff_widget_slot_for('card', product,
     * loop.index0) }}…{% endfor %}`). Index 0 is the editable card; index > 0 paints marked copies.
     */
    private static function mffWidgetSlotFor(): TwigFunction {
        return new TwigFunction(
            'mff_widget_slot_for',
            static function (Environment $env, array $context, string $slotId, mixed $product = null, int $index = 0): Markup {
                return new Markup(WidgetSlotRenderer::renderRepeated($env, $context, $slotId, $product, $index), 'UTF-8');
            },
            ['needs_environment' => true, 'needs_context' => true, 'is_safe' => ['html']]
        );
    }

    /** `mff_repeat_index()` — the card index `mff_widget_slot_for` is painting, null outside one. Read by the
     *  widgets macro (copies drop their canvas markers) and by widgets that build HTML ids (suffix per card). */
    private static function mffRepeatIndex(): TwigFunction {
        return new TwigFunction('mff_repeat_index', static fn(): ?int => WidgetSlotRenderer::repeatIndex());
    }
}
