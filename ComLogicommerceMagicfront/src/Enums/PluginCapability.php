<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

/**
 * Lo que este complemento sabe pintar y que un complemento anterior no sabía. Cada tienda lleva su propia versión del
 * complemento, así que el editor no puede dar por hecho que una pieza nueva sale en la tienda: este complemento la
 * declara en el modo editor (`<meta name="mff-plugin-capabilities">`, magicfront.html.twig) y el lienzo se la pasa al
 * editor. Un complemento sin ese meta es la VERSIÓN ANTERIOR: no declara nada y no se le ofrece nada nuevo.
 *
 * Una capacidad se añade aquí cuando el complemento empieza a pintar algo que el editor sólo debe ofrecer donde se ve, y
 * no se quita nunca: un editor nuevo con una tienda que aún no ha subido de complemento sigue preguntando por ella.
 * Contrato compartido con magicfront-frontend (`PluginCapability` en src/canvas/core/storefront-contract.ts).
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
enum PluginCapability: string {

    /** El desplegable de categorías de la cabecera (categoryDropdown@1) y su vista sola en Personalización. */
    case CategoryDropdown = 'categoryDropdown';

    /** Todas, separadas por espacios: el `content` del meta. */
    public static function declared(): string {
        return implode(' ', array_map(static fn(self $capability): string => $capability->value, self::cases()));
    }
}
