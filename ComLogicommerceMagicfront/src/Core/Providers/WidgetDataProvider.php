<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;

/**
 * A per-widget-type data provider. The MagicFront controller (via MagicfrontTrait) collects the
 * widget TYPES present on the page, then for each provider whose {@see self::appliesTo()} is true
 * enqueues its batch requests ({@see self::addBatch()}) and, once resolved, attaches the data onto
 * the widget Page DTOs ({@see self::attach()}, via PageRelationResolver / Page setters → `page.*`).
 *
 * Providers run SERVER-SIDE in the plugin (FWK/SDK available); the widget templates still consume
 * only `page.*`, so the docker preview renderer is unaffected (no FWK globals leak into widgets).
 * Keying data by widget type — not by route — lets session/global widgets (account, address,
 * user panel) render on ANY page; entity-bound providers read {@see ProviderContext::routeEntity()}
 * and fall back to per-widget settings when off-route.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
interface WidgetDataProvider {

    /**
     * Widget types this provider serves (e.g. ['addressBook', 'accountEdit', 'userPanel']).
     *
     * @return string[]
     */
    public function handledTypes(): array;

    /** Whether this provider should run for the current page (default: any handled type is present). */
    public function appliesTo(ProviderContext $ctx): bool;

    /** Enqueue the batch requests this provider needs (keyed by its own result keys). */
    public function addBatch(BatchRequests $requests, ProviderContext $ctx): void;

    /** Resolve the batch results and attach them onto the widget Page DTOs. */
    public function attach(?ElementCollection $pages, ProviderContext $ctx): void;

    /**
     * This provider's contribution to the page-level `shared` container (data the FWK globals do
     * not carry — e.g. countries, location settings, company roles). Merged across providers and
     * exposed to every widget as `shared.*`. Account/session data is NOT here (widgets read the
     * FWK `session` global directly).
     *
     * @return array
     */
    public function sharedData(ProviderContext $ctx): array;
}
