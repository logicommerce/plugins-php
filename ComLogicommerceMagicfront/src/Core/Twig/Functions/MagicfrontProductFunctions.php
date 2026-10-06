<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Twig\Functions;

use Plugins\ComLogicommerceMagicfront\Core\Resources\RenderMode;
use Plugins\ComLogicommerceMagicfront\Core\Twig\ContextBuilder;
use Twig\Environment;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * The product-detail derivations, computed ONCE here instead of in every widget's Twig.
 *
 * `page.product` stays the raw SDK `Product` DTO (LC's contract). What the widgets used to derive by
 * hand — which price to show (with or without taxes is a COMMERCE setting), whether there is stock
 * (per combination, never `definition.active`), the SKU of the chosen combination, the spec names
 * (the DTO carries `customTagValues` without names), the JSON the LC buy form expects — lives here.
 * Until 16-09-2026 four widgets each copied the price rule, `productRating` read a field that does not
 * exist and `productSpecs` read another one: with the "field missing → paint the demo" fallback they
 * showed demo data in production and nobody saw it.
 *
 * Every function reads BOTH a DTO object (storefront, canvas) and a plain nested array (docker
 * renderer, where the SDK is not loaded), see {@see self::read()}. No SDK/FWK import at file level:
 * this file is synced into the docker renderer.
 */
class MagicfrontProductFunctions {

    /** Storefront: honour the commerce's "show prices with taxes" setting; docker: taxes included. */
    /**
     * Los ajustes de stock del COMERCIO, que mandan sobre lo que la ficha puede decir:
     * `stockSystem` decide si la tienda habla por unidades o por el texto de disponibilidad,
     * `stockManagement` apaga el stock para toda la tienda, `allowReservations` permite comprar sin
     * existencias y `catalogStockPolicy` dice si las unidades visibles suman todos los almacenes.
     * Sin escaparate detrás (docker, lab) se devuelven los valores que LogiCommerce trae por defecto.
     */
    private static function stockSettings(): array {
        $defaults = ['stockSystem' => 'UNITS', 'stockManagement' => true, 'allowReservations' => false,
                     'catalogStockByWarehouse' => false, 'onlyInStock' => false];
        if (!class_exists('\\SDK\\Application')) {
            return $defaults;
        }
        try {
            $settings = \SDK\Application::getInstance()->getEcommerceSettings()->getStockSettings();
        } catch (\Throwable $e) {
            return $defaults;
        }
        return [
            'stockSystem'             => (string) $settings->getStockSystem(),
            'stockManagement'         => (bool) $settings->getStockManagement(),
            'allowReservations'       => (bool) $settings->getAllowReservations(),
            'catalogStockByWarehouse' => $settings->getCatalogStockPolicy() === 'BY_ASSIGNED_WAREHOUSES',
            'onlyInStock'             => (bool) $settings->getOnlyInStock(),
        ];
    }

    /**
     * Si la tienda tiene activos los avisos de stock: `themeConfiguration.commerce.showStockAlert` del tema del
     * comercio, la llave con la que el legado decide si pinta el «avísame» (productPageTop, SHOW_STOCK_ALERT). No
     * vive en el FWK: cada tema declara su propio Commerce, así que un tema sin el getter cuenta como apagado,
     * igual que su valor por defecto en TcDefaultResponsive. El renderer docker del lienzo no tiene tema: allí
     * decide sólo la combinación. El simulador de la muestra puede pedir «tienda sin avisos» con
     * `mffStockAlerts: false` (SampleProductSituation), el único campo de la muestra fuera de la forma del SDK.
     */
    private static function stockAlertsEnabled(mixed $product): bool {
        if (self::read($product, 'mffStockAlerts') === false) {
            return false;
        }
        if (!class_exists('\\FWK\\Core\\Theme\\Theme')) {
            return true;
        }
        try {
            $commerce = \FWK\Core\Theme\Theme::getInstance()->getConfiguration()->getCommerce();
        } catch (\Throwable $e) {
            return false;
        }
        return is_object($commerce) && method_exists($commerce, 'getShowStockAlert')
            && (bool) $commerce->getShowStockAlert();
    }

    private static function taxesIncluded(): bool {
        if (class_exists('\FWK\Core\ViewHelpers\ViewHelper') && method_exists('\FWK\Core\ViewHelpers\ViewHelper', 'getApplicationTaxesIncluded')) {
            return (bool) \FWK\Core\ViewHelpers\ViewHelper::getApplicationTaxesIncluded();
        }
        return true;
    }

    /**
     * @return TwigFunction[]
     */
    public static function all(ContextBuilder $ctx): array {
        return [
            new TwigFunction('mff_product_prices', [self::class, 'prices']),
            new TwigFunction('mff_product_stock', [self::class, 'stock']),
            new TwigFunction('mff_product_sku', [self::class, 'sku']),
            new TwigFunction('mff_product_flags', [self::class, 'flags']),
            new TwigFunction('mff_product_images', [self::class, 'images']),
            new TwigFunction('mff_product_specs', [self::class, 'specs']),
            new TwigFunction('mff_product_form_json', [self::class, 'formJson'], ['is_safe' => ['html']]),
            new TwigFunction('mff_product_options', [self::class, 'options']),
            new TwigFunction('mff_product_buy', [self::class, 'buy']),
            new TwigFunction('mff_product_buy_json', [self::class, 'buyJson'], ['is_safe' => ['html']]),
            new TwigFunction('mff_product_wishlist', [self::class, 'wishlist']),
            new TwigFunction('mff_product_siblings', [self::class, 'siblings']),
        ];
    }

    // ─── Derivations ───────────────────────────────────────────────────────

    /**
     * `{price, basePrice, hasOffer, discountPct, taxIncluded, alternative: {price, basePrice}, tiers: [{from, price}]}`
     * or null when the product has no price OR the merchant hid it (`definition.showPrice`). Prices come from
     * the CHOSEN combination when the DTO carries `combinationData`, else from the product.
     *
     * Se enseña lo que se COBRA: el precio de venta. El base sale tachado sólo si el producto está en oferta
     * y es mayor, y con `showBasePrice` apagado no sale nunca. El `getBuyPrice` del FWK pinta el base
     * cuando no hay oferta; con una customización que SUBE el precio (+10 %) la ficha enseñaba el base y
     * la cesta cobraba otro. Decisión de producto del 24-09-2026: la ficha no se separa de la cesta.
     */
    public static function prices(mixed $product): ?array {
        if ($product === null || !(bool) (self::read($product, 'definition.showPrice') ?? true)) {
            return null;
        }
        $taxIncluded = self::taxesIncluded();
        $shown   = self::itemPrices($product, $taxIncluded ? 'pricesWithTaxes' : 'prices');
        if ($shown === null) {
            return null;
        }
        $other   = self::itemPrices($product, $taxIncluded ? 'prices' : 'pricesWithTaxes');
        $offer   = (bool) (self::read($product, 'definition.offer') ?? false);
        $showBase = (bool) (self::read($product, 'definition.showBasePrice') ?? true);
        [$price, $basePrice] = self::charged($shown['base'], $shown['retail'], $offer);
        $hasOffer = $showBase && $basePrice > $price + 0.005;
        $tiers = [];
        foreach ($shown['tiers'] as $tier) {
            $tiers[] = ['from' => $tier['from'], 'price' => self::charged($tier['base'], $tier['retail'], $offer)[0]];
        }
        $alternative = null;
        if ($other !== null) {
            [$altPrice, $altBase] = self::charged($other['base'], $other['retail'], $offer);
            $alternative = ['price' => $altPrice, 'basePrice' => $altBase];
        }
        return [
            'price'       => $price,
            'basePrice'   => $hasOffer ? $basePrice : $price,
            'hasOffer'    => $hasOffer,
            'discountPct' => $hasOffer && $basePrice > 0 ? (int) round((1 - $price / $basePrice) * 100) : 0,
            'taxIncluded' => $taxIncluded,
            'alternative' => $alternative,
            'tiers'       => $tiers,
        ];
    }

    /**
     * Los importes de un modo de impuestos (`prices` o `pricesWithTaxes`): `{base, retail, tiers: [{from,
     * base, retail}]}`, de la combinación elegida si el DTO la trae y si no del producto. El importe vive en
     * `<modo>.prices.*`: los importes planos `<modo>.retailPrice` de `combinationData` están OBSOLETOS en el
     * SDK y valen 0 cuando la API ya no los manda, así que leerlos primero enseñaba un 0 sin avisar.
     */
    private static function itemPrices(mixed $product, string $mode): ?array {
        $productTiers = self::tiersOf(self::read($product, "{$mode}.pricesByQuantity"));
        $combination  = self::read($product, 'combinationData');
        foreach ([$combination, $product] as $source) {
            $retail = self::num(self::read($source, "{$mode}.prices.retailPrice"));
            $base   = self::num(self::read($source, "{$mode}.prices.basePrice"));
            if ($retail === null && $base === null) {
                continue;
            }
            $tiers = self::tiersOf(self::read($source, "{$mode}.pricesByQuantity"));
            $base ??= $retail;
            $retail ??= $base;
            if ($source === $product) {
                // El precio del producto no lleva los recargos de las opciones que vienen elegidas por
                // defecto; `getBuyPrice` del FWK se los suma (`defaultOptionsPrices`), y aquí también.
                $base   += self::num(self::read($product, "defaultOptionsPrices.{$mode}.basePrice")) ?? 0.0;
                $retail += self::num(self::read($product, "defaultOptionsPrices.{$mode}.retailPrice")) ?? 0.0;
            }
            return ['base' => $base, 'retail' => $retail, 'tiers' => $tiers ?: $productTiers];
        }
        return null;
    }

    /** `pricesByQuantity[]` del DTO como `[{from, base, retail}]`, de menos a más unidades. */
    private static function tiersOf(mixed $list): array {
        $tiers = [];
        foreach ((array) ($list ?? []) as $tier) {
            $from   = (int) (self::read($tier, 'quantity') ?? 0);
            $retail = self::num(self::read($tier, 'prices.retailPrice'));
            $base   = self::num(self::read($tier, 'prices.basePrice'));
            if ($from > 0 && ($retail !== null || $base !== null)) {
                $tiers[] = ['from' => $from, 'base' => $base ?? $retail, 'retail' => $retail ?? $base];
            }
        }
        usort($tiers, static fn(array $a, array $b): int => $a['from'] <=> $b['from']);
        return $tiers;
    }

    /** `[lo que se cobra, el precio base que se enseña]`: el base sólo cuenta si hay oferta y es mayor. */
    private static function charged(float $base, float $retail, bool $offer): array {
        return [$retail, $offer && $base > $retail ? $base : $retail];
    }

    /**
     * `{available, units, status, stockManagement, backorder}` — "available" is the CHOSEN combination's
     * stock (or the product's total stock when it has no combinations), never `definition.active`.
     */
    /**
     * A date as ISO text. The store hands the SDK's Date object (casting it to string was fatal on any product with a
     * forecast date); the docker renderer hands the JSON string as it came.
     */
    private static function dateText(mixed $date): string {
        if (is_object($date)) {
            if (method_exists($date, 'getDateTime') && $date->getDateTime() instanceof \DateTimeInterface) {
                return $date->getDateTime()->format(\DateTimeInterface::ATOM);
            }
            if (method_exists($date, 'format')) {
                return (string) $date->format('Y-m-d\\TH:i:sP');
            }
            return method_exists($date, '__toString') ? (string) $date : '';
        }
        return is_string($date) ? $date : '';
    }

    public static function stock(mixed $product): ?array {
        if ($product === null) {
            return null;
        }
        $settings   = self::stockSettings();
        $management = (bool) (self::read($product, 'definition.stockManagement') ?? false)
                      && $settings['stockManagement'];
        $backorder  = (string) (self::read($product, 'definition.backorder') ?? 'NONE');
        $status     = (string) (self::read($product, 'combinationData.status') ?? '');
        // Sin combinación resuelta NO hay cifra que enseñar: el stock total del producto es la suma de
        // todas sus combinaciones y decirlo como si fuera el de la elegida es mentir por exceso.
        $units      = (int) (self::read($product, 'combinationData.stock.units') ?? 0);
        $resolved   = self::read($product, 'combinationData.stock.units') !== null;
        // «Comprable» es lo mismo que en LogiCommerce: disponible O reservable. Tratar la reserva como
        // agotado hacía que la ficha se contradijera consigo misma (el botón compraba y el stock decía
        // que no). Sin estado resuelto se cae a las reglas del producto.
        // Sin stock se puede comprar si la tienda admite reservas Y el producto las acepta, o si el
        // producto se sirve bajo pedido: la regla de `BuyFormOptions::purchasableWithoutStock` del FWK.
        $alerts     = self::stockAlertsEnabled($product);
        $onRequest  = (bool) (self::read($product, 'definition.onRequest') ?? false);
        $withoutStock = ($settings['allowReservations'] && $backorder !== 'NONE') || $onRequest;
        $available  = $status !== ''
            ? in_array($status, ['AVAILABLE', 'RESERVE'], true)
            : (!$management || $units > 0 || $withoutStock);
        return [
            'available'       => $available,
            'units'           => $units,
            'resolved'        => $resolved,
            'status'          => $status,
            'stockManagement' => $management,
            'backorder'       => $backorder,
            'onRequest'       => $onRequest,
            'withoutStock'    => $withoutStock,
            'onRequestDays'   => (int) (self::read($product, 'definition.onRequestDays') ?? 0),
            'previsionDate'   => self::dateText(self::read($product, 'combinationData.stock.previsionDate')),
            'offsetDays'      => (int) (self::read($product, 'combinationData.stock.offsetDays') ?? 0),
            // El «avísame» exige las dos llaves del legado: que la combinación lo pida (lo calcula la API) Y que
            // la tienda tenga los avisos de stock activos en su tema. Sin la segunda, LogiCommerce sólo dice
            // «Sin stock» con la compra desactivada, y la ficha de MagicFront debe decir lo mismo.
            'showStockAlert'  => (bool) (self::read($product, 'combinationData.showStockAlert') ?? false)
                                 && $alerts,
            // La tienda no tiene los avisos de stock activos: el widget del aviso se pinta sin el gancho del motor
            // de ficha, que de lo contrario lo encendería al cambiar de combinación con el dato de la API.
            'alertsOff'       => !$alerts,
            'combinationId'   => (int) (self::read($product, 'combinationData.stock.combinationId') ?? 0),
            'settings'        => $settings,
            'availability'    => self::availability($product, $units, $resolved),
            // Si el producto tiene tramos aunque la combinación aún no esté resuelta: el widget deja
            // preparado dónde escribirlos para cuando el comprador elija.
            'hasIntervals'    => (bool) (self::read($product, 'definition.availability.intervals') ?? []),
        ];
    }

    /**
     * El TRAMO de disponibilidad que le toca a las unidades resueltas.
     *
     * La disponibilidad no es un estado nuestro: es una entidad que el comerciante configura una vez en
     * su backoffice, con N tramos por unidades y, en cada uno, su nombre, su descripción y su icono por
     * idioma. Aquí sólo se elige el tramo y se sustituyen los dos marcadores que LogiCommerce admite en
     * esos textos (`{{stock}}` y `{{onRequestDays}}`).
     *
     * El criterio es el del escaparate vivo: los tramos declaran su TOPE de unidades y se recorren de
     * mayor a menor quedándose con el primero que cubre (`units <= stock`). El Twig de servidor de
     * LogiCommerce usa otro que difiere en los bordes, pero nace oculto y manda el JavaScript.
     *
     * `null` = el producto no tiene definición asignada; entonces el widget usa sus propios textos.
     */
    private static function availability(mixed $product, int $units, bool $resolved): ?array {
        $intervals = (array) (self::read($product, 'definition.availability.intervals') ?? []);
        if (!$intervals || !$resolved) {
            return null;
        }
        $sorted = [];
        foreach ($intervals as $interval) {
            $sorted[] = ['stock' => (int) (self::read($interval, 'stock') ?? 0), 'raw' => $interval];
        }
        usort($sorted, static fn(array $a, array $b): int => $b['stock'] <=> $a['stock']);
        $chosen = null;
        foreach ($sorted as $candidate) {
            if ($units <= $candidate['stock']) {
                $chosen = $candidate['raw'];
            }
        }
        if ($chosen === null) {
            return null;
        }
        // `{{onRequestDays}}` son los días del bajo pedido y nada más: el FWK no les suma el plazo del
        // almacén (ése va en su propio aviso de entrega). Sumarlos daba 8 donde la tienda dice 5.
        $days = (int) (self::read($product, 'definition.onRequestDays') ?? 0);
        $fill = static fn(string $text): string => str_replace(
            ['{{stock}}', '{{onRequestDays}}'],
            [(string) $units, (string) $days],
            $text
        );
        return [
            'name'        => $fill((string) (self::read($chosen, 'language.name') ?? '')),
            'description' => $fill((string) (self::read($chosen, 'language.description') ?? '')),
            'image'       => (string) (self::read($chosen, 'language.image') ?? ''),
        ];
    }

    /** The chosen combination's SKU, else the product's; '' when none. */
    public static function sku(mixed $product): string {
        $sku = self::read($product, 'combinationData.productCodes.sku');
        if (!is_string($sku) || $sku === '') {
            $sku = self::read($product, 'codes.sku');
        }
        return is_string($sku) ? $sku : '';
    }

    /**
     * `{logged, inList}` — the favourites button's starting state: whether the shopper has a session (without
     * one, LogiCommerce keeps no favourites and the button sends them to log in) and whether this product is
     * already in their DEFAULT list, the one LC's own «add to favourites» fills. Read from the session's
     * aggregate data, the same list LC's theme reads; without a storefront behind (docker, lab) nobody is
     * logged in.
     *
     * Why not `page.wishlist` (ProductController::routeWishlist): that one is attached only when the whole
     * product ROUTE renders, so a widget the editor repaints on its own (getWidget / getWidgets) or the docker
     * renderer never sees it. A Twig function answers in every render path.
     */
    public static function wishlist(mixed $product): array {
        $none = ['logged' => false, 'inList' => false];
        // The editor's simulated shopper (canvas only): logged in with this product in their favourites, so every
        // widget that reads it paints the «in the list» state the store would paint.
        // Literal param name and a guarded RenderMode: this file also runs in the docker renderer (plugin-src), which
        // loads Core/Twig and Enums but neither Core/Resources nor the SDK Enum that SampleSituationParam extends —
        // referencing either class there broke every widget that calls this function (28-09).
        if ($product !== null && ($_GET['mff_sample_wishlist'] ?? '') === 'in'
            && class_exists(RenderMode::class) && RenderMode::isCanvasMode()) {
            return ['logged' => true, 'inList' => true];
        }
        $sessionClass = '\\FWK\\Core\\Resources\\Session';
        if ($product === null || !class_exists($sessionClass)) {
            return $none;
        }
        try {
            $session = $sessionClass::getInstance();
            if (!$session->isLogged()) {
                return $none;
            }
            $lists = $session->getAggregateData()->getShoppingLists();
            $ids = $lists?->getDefaultOne()?->getProductIdList() ?? [];
        } catch (\Throwable $e) {
            return $none;
        }
        $id = self::read($product, 'id');
        return ['logged' => true, 'inList' => $id !== null && in_array((int) $id, array_map('intval', (array) $ids), true)];
    }

    /**
     * The product's SIBLINGS — the same product in other colours, sizes or finishes, which LogiCommerce models
     * as separate products the merchant gathers in a related-items GROUP named by them ("Colors"). `$groups` is
     * `page.productRelatedGroups` (`[{name, products}]`); `$groupName` is what the merchant typed in the widget,
     * matched against the group names ignoring case, accents and surrounding spaces.
     *
     * Returns `{matched, groupNames, items: [{id, name, url, image, current}]}`: the route product is part of
     * the set and marked `current` (added first when the merchant left it out of its own group). `groupNames`
     * lists the product's groups so the editor can tell the merchant what to type when nothing matched.
     */
    public static function siblings(mixed $groups, mixed $groupName, mixed $product): array {
        $names  = [];
        $picked = null;
        $wanted = self::foldName((string) ($groupName ?? ''));
        foreach (is_iterable($groups) ? $groups : [] as $group) {
            $name = trim((string) (self::read($group, 'name') ?? ''));
            if ($name === '') {
                continue;
            }
            $names[] = $name;
            if ($picked === null && $wanted !== '' && self::foldName($name) === $wanted) {
                $picked = $group;
            }
        }
        $result = ['matched' => $picked !== null, 'groupNames' => $names, 'items' => [],
            'notice' => $picked === null ? self::siblingsNotice((string) ($groupName ?? ''), $names) : ''];
        if ($picked === null) {
            return $result;
        }
        $currentId = (int) (self::read($product, 'id') ?? 0);
        $items = [];
        $hasCurrent = false;
        foreach ((array) (self::read($picked, 'products') ?? []) as $sibling) {
            $item = self::siblingItem($sibling, $currentId);
            if ($item === null) {
                continue;
            }
            $hasCurrent = $hasCurrent || $item['current'];
            $items[] = $item;
        }
        if (!$hasCurrent && $product !== null) {
            $self = self::siblingItem($product, $currentId);
            if ($self !== null) {
                array_unshift($items, $self);
            }
        }
        $result['items'] = $items;
        return $result;
    }

    /**
     * What the EDITOR tells the merchant when the widget has nothing to show, in the language being edited: which
     * group it looked for, the groups this product has, and what to do. Only the canvas paints it; in the shop the
     * block is simply not shown. Never the developer text of the property it replaced.
     */
    private static function siblingsNotice(string $groupName, array $groupNames): string {
        $texts = [
            'es' => ['empty' => 'Escribe en «Nombre del grupo» el grupo de relacionados que reúne las variantes de este producto.',
                'missing' => 'Este producto no tiene el grupo de relacionados «%s».',
                'pick' => 'Elige uno de sus grupos en «Nombre del grupo»: %s.',
                'none' => 'Este producto no tiene grupos de relacionados: en la tienda este bloque no se mostrará.'],
            'ca' => ['empty' => 'Escriu a «Nom del grup» el grup de relacionats que reuneix les variants d\'aquest producte.',
                'missing' => 'Aquest producte no té el grup de relacionats «%s».',
                'pick' => 'Tria un dels seus grups a «Nom del grup»: %s.',
                'none' => 'Aquest producte no té grups de relacionats: a la botiga aquest bloc no es mostrarà.'],
            'en' => ['empty' => 'Type in «Group name» the related-items group that gathers this product\'s variants.',
                'missing' => 'This product has no related-items group «%s».',
                'pick' => 'Pick one of its groups in «Group name»: %s.',
                'none' => 'This product has no related-items groups: in the shop this block will not show.'],
        ];
        $t = $texts[self::editingLanguage()] ?? $texts['en'];
        if ($groupNames === []) {
            return $t['none'];
        }
        $first = trim($groupName) === '' ? $t['empty'] : sprintf($t['missing'], trim($groupName));
        return $first . ' ' . sprintf($t['pick'], implode(' · ', $groupNames));
    }

    /** The language being rendered (the session's), or `en` where there is no session (the docker renderer). */
    private static function editingLanguage(): string {
        try {
            if (class_exists(\FWK\Core\Resources\Session::class)) {
                $code = \FWK\Core\Resources\Session::getInstance()->getGeneralSettings()->getLanguage();
                if (is_string($code) && $code !== '') {
                    return strtolower(substr($code, 0, 2));
                }
            }
        } catch (\Throwable) {
            // No session here: the neutral language.
        }
        return 'en';
    }

    private static function siblingItem(mixed $product, int $currentId): ?array {
        $name = trim((string) (self::read($product, 'language.name') ?? ''));
        if ($name === '') {
            return null;
        }
        $image = '';
        foreach (['smallImage', 'mediumImage', 'largeImage'] as $size) {
            $candidate = (string) (self::read($product, 'mainImages.' . $size) ?? '');
            if ($candidate !== '') {
                $image = $candidate;
                break;
            }
        }
        $id = (int) (self::read($product, 'id') ?? 0);
        $url = (string) (self::read($product, 'language.urlSeo') ?? '');
        return ['id' => $id, 'name' => $name, 'url' => $url, 'image' => $image, 'current' => $id === $currentId];
    }

    /** Lower case, no accents, single spaces: "  Colores " and "colores" and "COLÓRES" are the same group. */
    private static function foldName(string $name): string {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if (class_exists('\\Normalizer')) {
            $name = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($name, \Normalizer::FORM_D) ?: $name) ?? $name;
        }
        return mb_strtolower($name);
    }

    /** `{offer, featured, discountPct, offerEndsAt}` — the ribbons and the countdown. */
    public static function flags(mixed $product): array {
        $prices = self::prices($product);
        // The SDK's Date has no format(): the old check never matched and the countdown never existed in the store.
        $ends   = self::dateText(self::read($product, 'definition.endOfferDate'));
        return [
            'offer'       => (bool) (self::read($product, 'definition.offer') ?? false),
            'featured'    => (bool) (self::read($product, 'definition.featured') ?? false),
            'discountPct' => $prices['discountPct'] ?? 0,
            'offerEndsAt' => is_string($ends) && $ends !== '' ? $ends : null,
        ];
    }

    /**
     * Ordered gallery: main image, additional images, then one image per option value that has one
     * (`optionValueId` set, so the engine can jump to it when that value is chosen).
     * `[{src, thumb, alt, optionValueId}]`.
     */
    public static function images(mixed $product): array {
        $out  = [];
        $name = (string) (self::read($product, 'language.altImageKeywords') ?: self::read($product, 'language.name') ?: '');
        $main = self::read($product, 'mainImages.largeImage');
        if (is_string($main) && $main !== '') {
            $out[] = ['src' => $main, 'thumb' => (string) (self::read($product, 'mainImages.smallImage') ?: $main), 'alt' => $name, 'optionValueId' => null];
        }
        foreach ((array) (self::read($product, 'additionalImages') ?? []) as $img) {
            $src = self::read($img, 'largeImage');
            if (is_string($src) && $src !== '') {
                $alt   = (string) (self::read($img, 'alt') ?: $name);
                $out[] = ['src' => $src, 'thumb' => (string) (self::read($img, 'smallImage') ?: $src), 'alt' => $alt, 'optionValueId' => null];
            }
        }
        foreach ((array) (self::read($product, 'options') ?? []) as $option) {
            foreach ((array) (self::read($option, 'values') ?? []) as $value) {
                $src = self::read($value, 'images.largeImage');
                if (is_string($src) && $src !== '') {
                    $out[] = [
                        'src'           => $src,
                        'thumb'         => (string) (self::read($value, 'images.smallImage') ?: $src),
                        'alt'           => (string) (self::read($value, 'language.value') ?: $name),
                        'optionValueId' => (int) (self::read($value, 'id') ?? 0),
                    ];
                }
            }
        }
        return $out;
    }

    /**
     * The technical sheet, GROUPED as the merchant grouped it in the backoffice. The product's
     * `customTagValues[]` (`CatalogCustomTagValue`: value / values[] / image, position, groupId / groupName /
     * groupPosition) become `[{id, name, position, rows: [{pId, name, type, value, checked, values:
     * [{value, image}], image}]}]`: groups in `groupPosition` order with the ungrouped tags (`id` 0,
     * empty `name` — the widget titles them) LAST, rows in `position` order, a tag with no value at all
     * skipped. `$tags` is `page.productCustomTags`: pId → `{name, controlType}` from the commerce's
     * `GET /customTags?type=PRODUCT` (the DTO of the product carries neither); without it the pId is the
     * name and the type is empty (plain text).
     */
    public static function specs(mixed $product, ?array $tags = null): array {
        $tags ??= [];
        $groups = [];
        foreach ((array) (self::read($product, 'customTagValues') ?? []) as $tag) {
            $pId   = (string) (self::read($tag, 'customTagPId') ?? '');
            $meta  = is_array($tags[$pId] ?? null) ? $tags[$pId] : [];
            $raw   = self::read($tag, 'value');
            $value = is_scalar($raw) ? trim((string) $raw) : '';
            $image = (string) (self::read($tag, 'image') ?? '');
            $values = [];
            foreach ((array) (self::read($tag, 'values') ?? []) as $selected) {
                $selectedValue = trim((string) (self::read($selected, 'value') ?? ''));
                $selectedImage = (string) (self::read($selected, 'image') ?? '');
                if ($selectedValue !== '' || $selectedImage !== '') {
                    $values[] = ['value' => $selectedValue, 'image' => $selectedImage];
                }
            }
            if ($value === '' && $values === [] && $image === '') {
                continue;
            }
            $groupId = (int) (self::read($tag, 'groupId') ?? 0);
            $key     = 'g' . max(0, $groupId);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id'       => max(0, $groupId),
                    'name'     => $groupId > 0 ? (string) (self::read($tag, 'groupName') ?? '') : '',
                    'position' => (int) (self::read($tag, 'groupPosition') ?? 0),
                    'rows'     => [],
                ];
            }
            $name = (string) ($meta['name'] ?? '');
            $groups[$key]['rows'][] = [
                'pId'      => $pId,
                'name'     => $name !== '' ? $name : $pId,
                'type'     => (string) ($meta['controlType'] ?? ''),
                'value'    => $value,
                'checked'  => in_array(strtolower($value), ['1', 'true', 'yes'], true),
                'values'   => $values,
                'image'    => $image,
                'position' => (int) (self::read($tag, 'position') ?? 0),
            ];
        }
        foreach ($groups as &$group) {
            usort($group['rows'], static fn(array $a, array $b): int => $a['position'] <=> $b['position']);
        }
        unset($group);
        $list = array_values($groups);
        usort($list, static fn(array $a, array $b): int => [$a['id'] === 0, $a['position'], $a['id']] <=> [$b['id'] === 0, $b['position'], $b['id']]);
        return $list;
    }

    /**
     * The `data-product` JSON the LC buy form (`lc.forms.js`) expects — the FWK's own `ProductJsonData`
     * projection, which the plugin already computed in `page.productJson` but no widget used. In docker
     * (no FWK) a minimal projection of the same shape keeps the markup valid.
     */
    public static function formJson(mixed $product): Markup {
        if (is_object($product) && class_exists('\FWK\ViewHelpers\Product\ProductJsonData')) {
            $json = (new \FWK\ViewHelpers\Product\ProductJsonData($product))->output();
            return new Markup(self::jsonAttr($json), 'UTF-8');
        }
        $minimal = [
            'id'         => (int) (self::read($product, 'id') ?? 0),
            'definition' => [
                'minOrderQuantity'      => (int) (self::read($product, 'definition.minOrderQuantity') ?? 1),
                'maxOrderQuantity'      => (int) (self::read($product, 'definition.maxOrderQuantity') ?? 0),
                'multipleOrderQuantity' => (int) (self::read($product, 'definition.multipleOrderQuantity') ?? 1),
            ],
            'options'    => [],
            'stocks'     => [],
        ];
        return new Markup(self::jsonAttr($minimal), 'UTF-8');
    }

    // ─── The buy primitives (options · quantity · add to cart) and their engine ────

    /**
     * The product's options as the `productOptions` widget paints them, with everything LogiCommerce
     * knows about them — not a subset:
     * `[{id, type, typology, name, prompt, required, combinable, showAsGrid, minValues, maxValues,
     *    image, values: [{id, value, description, image, price, available, selected, noReturn}]}]`
     * in `priority` order, values in their `priority` order.
     *
     * `typology` (SIZE / COLOR / MATERIAL / OTHER / WITHOUT_TYPOLOGY) is what the commerce already
     * declared the option MEANS, so the widget can paint a colour as swatches and a size as boxes
     * without the merchant configuring anything. `price` is the value's own surcharge in the shown tax
     * mode (0 when it adds nothing), which every competing product sheet prints next to the value.
     * `available` / `selected` come from the CHOSEN combination (`combinationData.options[].values[]`)
     * when the DTO carries them, else every value is available and none selected — the engine
     * (`mff_product_buy`) recomputes them offline.
     */
    public static function options(mixed $product): array {
        $taxIncluded = self::taxesIncluded();
        $states = [];
        foreach ((array) (self::read($product, 'combinationData.options') ?? []) as $option) {
            foreach ((array) (self::read($option, 'values') ?? []) as $value) {
                $states[(int) (self::read($value, 'id') ?? 0)] = [
                    'available' => (bool) (self::read($value, 'available') ?? true),
                    'selected'  => (bool) (self::read($value, 'selected') ?? false),
                ];
            }
        }
        $out = [];
        foreach ((array) (self::read($product, 'options') ?? []) as $option) {
            $values = [];
            foreach ((array) (self::read($option, 'values') ?? []) as $value) {
                $id = (int) (self::read($value, 'id') ?? 0);
                // El recargo del valor vive donde el resto de importes del DTO: `<modo>.prices.retailPrice`.
                $shown = $taxIncluded ? 'pricesWithTaxes' : 'prices';
                $values[] = [
                    'id'          => $id,
                    'value'       => (string) (self::read($value, 'language.value') ?? ''),
                    'description' => (string) (self::read($value, 'language.shortDescription') ?? ''),
                    'image'       => (string) (self::read($value, 'images.smallImage') ?? ''),
                    'price'       => (float) (self::num(self::read($value, "{$shown}.prices.retailPrice")) ?? 0),
                    'noReturn'    => (bool) (self::read($value, 'noReturn') ?? false),
                    'available'   => $states[$id]['available'] ?? true,
                    'selected'    => $states[$id]['selected'] ?? false,
                    'priority'    => (int) (self::read($value, 'priority') ?? 0),
                ];
            }
            usort($values, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
            $out[] = [
                'id'         => (int) (self::read($option, 'id') ?? 0),
                'type'       => (string) (self::read($option, 'type') ?? 'SELECTOR'),
                'typology'   => (string) (self::read($option, 'typology') ?? 'WITHOUT_TYPOLOGY'),
                'name'       => (string) (self::read($option, 'language.name') ?? ''),
                'prompt'     => (string) (self::read($option, 'language.prompt') ?? ''),
                'required'   => (bool) (self::read($option, 'required') ?? false),
                'combinable' => (bool) (self::read($option, 'combinable') ?? false),
                'showAsGrid' => (bool) (self::read($option, 'showAsGrid') ?? false),
                'minValues'  => (int) (self::read($option, 'minValues') ?? 0),
                'maxValues'  => (int) (self::read($option, 'maxValues') ?? 0),
                'image'      => (string) (self::read($option, 'image') ?? ''),
                'priority'   => (int) (self::read($option, 'priority') ?? 0),
                'values'     => $values,
            ];
        }
        usort($out, static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']);
        return $out;
    }

    /**
     * What the three buy primitives and the product-page engine need, in ONE shape, the same in the
     * storefront and in docker:
     * `{id, productType, live, quantity: {min, max, multiple}, stock: {managed, backorder, units, status,
     * combinationId, sku}, options: [{id, type, required, combinable, values: [id…]}],
     * combinations: [{id, values: [valueId…], units, sku}]}`.
     * `live` is true only on the real storefront route (not the canvas / preview, where the product is the
     * sample): there the engine asks LC (`get_product_combination_data`, `basket/add_product`); elsewhere
     * it resolves the combination from `combinations` and never calls anything.
     */
    public static function buy(mixed $product, bool $live = false): ?array {
        if ($product === null) {
            return null;
        }
        $stock = self::stock($product);
        $options = [];
        foreach (self::options($product) as $option) {
            $options[] = [
                'id'         => $option['id'],
                'type'       => $option['type'],
                'required'   => $option['required'],
                'combinable' => $option['combinable'],
                'values'     => array_map(static fn(array $v): int => $v['id'], $option['values']),
            ];
        }
        $combinations = [];
        foreach ((array) (self::read($product, 'combinations') ?? []) as $combination) {
            $values = [];
            foreach ((array) (self::read($combination, 'values') ?? []) as $value) {
                $values[] = (int) (self::read($value, 'productOptionValueId') ?? 0);
            }
            $units = 0;
            foreach ((array) (self::read($combination, 'stocks') ?? []) as $s) {
                $units += (int) (self::read($s, 'units') ?? 0);
            }
            $combinations[] = [
                'id'     => (int) (self::read($combination, 'id') ?? 0),
                'values' => $values,
                'units'  => $units,
                'sku'    => (string) (self::read($combination, 'codes.sku') ?? ''),
            ];
        }
        $multiple = max(1, (int) (self::read($product, 'definition.multipleOrderQuantity') ?? 1));
        $actsOver = max(0, (int) (self::read($product, 'definition.multipleActsOver') ?? 0));
        $min      = self::minimumQuantity(max(1, (int) (self::read($product, 'definition.minOrderQuantity') ?? 1)), $multiple, $actsOver);
        return [
            'id'           => (int) (self::read($product, 'id') ?? 0),
            'productType'  => (string) (self::read($product, 'productType') ?? 'PRODUCT'),
            'live'         => $live,
            // The product's own page: a listing card that cannot buy here (options still to choose and no options
            // widget in the card) takes the shopper there, like a store's «See options».
            'url'          => (string) (self::read($product, 'language.urlSeo') ?? ''),
            'quantity'     => [
                'min'      => $min,
                'max'      => (int) (self::read($product, 'definition.maxOrderQuantity') ?? 0),
                'multiple' => $multiple,
                // El múltiplo no se aplica desde la primera unidad: el comerciante dice a partir de
                // cuántas empieza a exigirse. En el DTO ese campo se llama `multipleActsOver`.
                'batch'    => $actsOver,
            ],
            // Si el comerciante apaga el formulario de compra, la ficha no debe enseñar ni cantidad ni
            // botón: es una decisión suya, no del diseño de la página.
            'showOrderBox' => (bool) (self::read($product, 'definition.showOrderBox') ?? true),
            // El comerciante también decide si se enseña el precio y el precio base: son decisiones suyas
            // por producto, no del diseño de la página.
            'showPrice'    => (bool) (self::read($product, 'definition.showPrice') ?? true),
            'showBasePrice' => (bool) (self::read($product, 'definition.showBasePrice') ?? true),
            'stock'        => [
                'managed'       => (bool) ($stock['stockManagement'] ?? false),
                'backorder'     => (string) ($stock['backorder'] ?? 'NONE'),
                'units'         => (int) ($stock['units'] ?? 0),
                'resolved'      => (bool) ($stock['resolved'] ?? false),
                'status'        => (string) ($stock['status'] ?? ''),
                'available'     => (bool) ($stock['available'] ?? false),
                'onRequest'     => (bool) ($stock['onRequest'] ?? false),
                'withoutStock'  => (bool) ($stock['withoutStock'] ?? false),
                'onRequestDays' => (int) ($stock['onRequestDays'] ?? 0),
                'previsionDate' => (string) ($stock['previsionDate'] ?? ''),
                'offsetDays'    => (int) ($stock['offsetDays'] ?? 0),
                'showAlert'     => (bool) ($stock['showStockAlert'] ?? false),
                'combinationId' => (int) ($stock['combinationId'] ?? 0),
                'sku'           => self::sku($product),
            ],
            // Lo que el COMERCIO decidió, que manda sobre lo que la ficha puede decir.
            'settings'     => $stock['settings'] ?? [],
            // El tramo de disponibilidad ya resuelto para estas unidades, o null si no hay definición.
            'availability' => $stock['availability'] ?? null,
            // Y la tabla entera de tramos, porque el cliente cambia de combinación sin recargar y el
            // motor tiene que volver a elegir tramo con el mismo criterio, no quedarse con el primero.
            'availabilityIntervals' => self::availabilityIntervals($product),
            'options'      => $options,
            'combinations' => $combinations,
            // Lo que hace falta para recalcular el precio sin preguntar a la tienda (lienzo, docker): los
            // importes del producto y de cada valor de opción, con sus tramos por cantidad, en los dos
            // modos de impuestos. Null si el comerciante oculta el precio: entonces no hay nada que pintar.
            'pricing'      => (bool) (self::read($product, 'definition.showPrice') ?? true) ? [
                'offer'         => (bool) (self::read($product, 'definition.offer') ?? false),
                'showBasePrice' => (bool) (self::read($product, 'definition.showBasePrice') ?? true),
                'withTaxes'     => self::pricingOf($product, 'pricesWithTaxes'),
                'withoutTaxes'  => self::pricingOf($product, 'prices'),
            ] : null,
            // Y cómo se escribe un importe en esta tienda, para darle la misma forma que `outputHtmlCurrency`.
            'format'       => self::priceFormat(),
            // Tamaño máximo (MB) del conjunto de ficheros de una opción de adjunto, el mismo límite del FWK.
            'attachmentMaxSize' => defined('ATTACHMENT_MAX_SIZE') ? (float) constant('ATTACHMENT_MAX_SIZE') : 0,
        ];
    }

    /**
     * El mínimo que de verdad se puede pedir, con la regla de `lc.forms.js`: si el múltiplo se exige desde
     * la primera unidad (`multipleActsOver` 0), el mínimo sube al múltiplo; si se exige a partir de N, sólo
     * se redondea cuando el mínimo ya llega a N.
     */
    private static function minimumQuantity(int $min, int $multiple, int $actsOver): int {
        if ($multiple <= 1) {
            return $min;
        }
        if ($actsOver > 0) {
            return ($min >= $actsOver && $min % $multiple !== 0) ? $min + ($multiple - $min % $multiple) : $min;
        }
        if ($min < $multiple) {
            return $multiple;
        }
        return $min % $multiple !== 0 ? $min + ($multiple - $min % $multiple) : $min;
    }

    /** `{base, retail, tiers, values: {valueId: {base, retail, tiers}}}` de un modo de impuestos. */
    private static function pricingOf(mixed $product, string $mode): ?array {
        $retail = self::num(self::read($product, "{$mode}.prices.retailPrice"));
        $base   = self::num(self::read($product, "{$mode}.prices.basePrice"));
        if ($retail === null && $base === null) {
            return null;
        }
        $values = [];
        foreach ((array) (self::read($product, 'options') ?? []) as $option) {
            foreach ((array) (self::read($option, 'values') ?? []) as $value) {
                $vRetail = self::num(self::read($value, "{$mode}.prices.retailPrice")) ?? 0.0;
                $vBase   = self::num(self::read($value, "{$mode}.prices.basePrice")) ?? $vRetail;
                $vTiers  = self::tiersOf(self::read($value, "{$mode}.pricesByQuantity"));
                if ($vRetail != 0.0 || $vBase != 0.0 || $vTiers) {
                    $values[(string) (int) (self::read($value, 'id') ?? 0)] = ['base' => $vBase, 'retail' => $vRetail, 'tiers' => $vTiers];
                }
            }
        }
        return [
            'base'   => $base ?? $retail,
            'retail' => $retail ?? $base,
            'tiers'  => self::tiersOf(self::read($product, "{$mode}.pricesByQuantity")),
            'values' => (object) $values,
        ];
    }

    /**
     * Idioma regional, moneda, símbolo y decimales con que la tienda escribe los importes: lo mismo que
     * lee `outputHtmlCurrency` del FWK. Sin escaparate (docker) se devuelve la forma de `mff_price`.
     */
    private static function priceFormat(): array {
        $format = ['locale' => 'es_ES', 'currency' => 'EUR', 'symbol' => '¤', 'minDecimals' => 2, 'maxDecimals' => 2];
        if (!class_exists('\\FWK\\Core\\Resources\\Session') || !class_exists('\\SDK\\Application')) {
            return $format;
        }
        try {
            $general = \FWK\Core\Resources\Session::getInstance()->getGeneralSettings();
            $format['locale']   = (string) $general->getLocale();
            $format['currency'] = (string) $general->getCurrency();
            $format['symbol']   = '';
            foreach (\SDK\Application::getInstance()->getCurrenciesSettings() as $currency) {
                if ($currency->getCode() === $format['currency']) {
                    $format['symbol'] = (string) $currency->getSymbol();
                    break;
                }
            }
            $format['minDecimals'] = defined('CURRENCY_DECIMALS_MIN_LENGTH') ? (int) constant('CURRENCY_DECIMALS_MIN_LENGTH') : 0;
            $format['maxDecimals'] = defined('CURRENCY_DECIMALS_MAX_LENGTH') ? (int) constant('CURRENCY_DECIMALS_MAX_LENGTH') : 0;
        } catch (\Throwable $e) {
            return $format;
        }
        return $format;
    }

    /**
     * Los tramos de disponibilidad tal como los configuró el comerciante, sin resolver: tope de unidades
     * y textos con sus marcadores intactos. El motor los necesita crudos porque resuelve de nuevo en cada
     * cambio de combinación, sin recargar la página.
     */
    private static function availabilityIntervals(mixed $product): array {
        $out = [];
        foreach ((array) (self::read($product, 'definition.availability.intervals') ?? []) as $interval) {
            $out[] = [
                'stock'       => (int) (self::read($interval, 'stock') ?? 0),
                'name'        => (string) (self::read($interval, 'language.name') ?? ''),
                'description' => (string) (self::read($interval, 'language.description') ?? ''),
                'image'       => (string) (self::read($interval, 'language.image') ?? ''),
            ];
        }
        usort($out, static fn(array $a, array $b): int => $a['stock'] <=> $b['stock']);
        return $out;
    }

    /**
     * Una fila por combinación, para la tabla de combinaciones (#1179): `{combinationId: {price, alternative,
     * tiers: [{from, price}], units, purchasable, label}}`. `[]` sin producto.
     *
     * El precio es el del motor de la ficha al elegir esa combinación: el del producto más el recargo de cada
     * uno de sus valores, los dos al tramo por cantidad que toque. Leer sólo el del producto pintaba todas las
     * filas a 299 € cuando el ancho sumaba +50 o +100. `price`/`alternative` son null si el comerciante oculta
     * el precio.
     *
     * `purchasable` es la regla del resumen de la ficha: sin gestión de stock, con unidades, o sin unidades si se
     * puede reservar o se sirve bajo pedido. `label` sólo se rellena para la fila sin unidades que aun así se
     * compra: el tramo de disponibilidad del comerciante para esas unidades y, sin tramo, la etiqueta «Reservar»
     * de la tienda — lo mismo que dice el resumen.
     *
     * Sin unidades, cuando la API trae el estado de la combinación elegida (#1232), manda ese estado: se compra sólo
     * si es `RESERVE`, que es lo que deciden el resumen y el botón de LogiCommerce. La regla daba «Reservar» en todas
     * las filas de un producto agotado cuyo botón decía «No disponible». Con unidades, la regla se queda.
     */
    public static function combinationRows(mixed $product): array {
        if ($product === null) {
            return [];
        }
        $showPrice = (bool) (self::read($product, 'definition.showPrice') ?? true);
        $taxIncluded = self::taxesIncluded();
        $shown = $showPrice ? self::pricingOf($product, $taxIncluded ? 'pricesWithTaxes' : 'prices') : null;
        $other = $showPrice ? self::pricingOf($product, $taxIncluded ? 'prices' : 'pricesWithTaxes') : null;
        $stock = self::stock($product);
        $managed = (bool) ($stock['stockManagement'] ?? false);
        $withoutStock = (bool) ($stock['withoutStock'] ?? false);
        $status = (string) ($stock['status'] ?? '');
        $rows = [];
        foreach ((array) (self::read($product, 'combinations') ?? []) as $combination) {
            $values = [];
            foreach ((array) (self::read($combination, 'values') ?? []) as $value) {
                $values[] = (int) (self::read($value, 'productOptionValueId') ?? 0);
            }
            $units = 0;
            foreach ((array) (self::read($combination, 'stocks') ?? []) as $s) {
                $units += (int) (self::read($s, 'units') ?? 0);
            }
            $purchasable = !$managed || $units > 0 || ($status !== '' ? $status === 'RESERVE' : $withoutStock);
            $label = '';
            if ($units <= 0 && $purchasable) {
                $label = (string) (self::availability($product, $units, true)['name'] ?? '');
                if ($label === '') {
                    $label = MagicfrontTwigFunctions::label('RESERVE');
                }
            }
            $tiers = [];
            foreach ($shown['tiers'] ?? [] as $tier) {
                $tiers[] = ['from' => $tier['from'], 'price' => self::rowPrice($shown, $values, $tier['from'])];
            }
            $rows[(string) (int) (self::read($combination, 'id') ?? 0)] = [
                'price'       => self::rowPrice($shown, $values, 1),
                'alternative' => self::rowPrice($other, $values, 1),
                'tiers'       => $tiers,
                'units'       => $units,
                'purchasable' => $purchasable,
                'label'       => $label,
            ];
        }
        return $rows;
    }

    /** Lo que se cobra por una combinación a `$quantity` unidades: el producto más el recargo de cada valor. */
    private static function rowPrice(?array $mode, array $valueIds, int $quantity): ?float {
        if ($mode === null) {
            return null;
        }
        $total = self::retailAt($mode['retail'], $mode['tiers'], $quantity);
        $values = (array) $mode['values'];
        foreach ($valueIds as $id) {
            $value = $values[(string) $id] ?? null;
            if ($value !== null) {
                $total += self::retailAt($value['retail'], $value['tiers'], $quantity);
            }
        }
        return $total;
    }

    /** El importe de venta a `$quantity` unidades: el del último tramo alcanzado, o el de una unidad. */
    private static function retailAt(float $retail, array $tiers, int $quantity): float {
        foreach ($tiers as $tier) {
            if ($quantity >= $tier['from']) {
                $retail = (float) $tier['retail'];
            }
        }
        return $retail;
    }

    /** `mff_product_buy` as JSON for a single-quoted `data-product='…'` attribute; `{}` without a product. */
    public static function buyJson(mixed $product, bool $live = false): Markup {
        $data = self::buy($product, $live);
        return new Markup($data === null ? '{}' : self::jsonAttr($data), 'UTF-8');
    }

    // ─── Reading a DTO or an array the same way ────────────────────────────

    /**
     * Dotted-path read over a DTO object (getters `getX()`/`isX()`, then public props) or a nested
     * array. Null when any segment is missing.
     */
    public static function read(mixed $subject, string $path): mixed {
        $current = $subject;
        foreach (explode('.', $path) as $segment) {
            if ($current === null) {
                return null;
            }
            if (is_array($current)) {
                $current = array_key_exists($segment, $current) ? $current[$segment] : null;
                continue;
            }
            if (!is_object($current)) {
                return null;
            }
            $getter = 'get' . ucfirst($segment);
            $is     = 'is' . ucfirst($segment);
            if (method_exists($current, $getter)) {
                $current = $current->{$getter}();
            } elseif (method_exists($current, $is)) {
                $current = $current->{$is}();
            } elseif (array_key_exists($segment, get_object_vars($current))) {
                // Only PUBLIC properties: property_exists() is also true for a protected SDK field, and reading it
                // from here is a fatal Error (paridad B7, 28-09).
                $current = $current->{$segment};
            } else {
                return null;
            }
        }
        return $current;
    }

    private static function num(mixed $value): ?float {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }

    /** JSON safe inside a single-quoted HTML attribute (the widgets write `data-product='…'`). */
    private static function jsonAttr(array $data): string {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_AMP);
        return $json === false ? '{}' : $json;
    }
}
