<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\PaymentCards;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Dtos\PluginProperties;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Enums\PluginConnectorType;
use SDK\Services\Parameters\Groups\PluginConnectorTypeParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Payment-cards data CARRIER for the `paymentCards` widget. Mirrors {@see \FWK\Controllers\User\PaymentCardsController}:
 * lists the active PAYMENT_SYSTEM plugins, then per plugin fetches the account's stored payment tokens +
 * plugin properties, and groups them (plugin → cards). Pure transport — raw `toArray()` per group, no reshaping.
 * NOT paginated. The widget owns display + the LC-owned deletePaymentCardForm; see the store
 * fwk/themes/core/macros/modes/bootstrap5/user/paymentCards.html.twig + each payment plugin's paymentCardProperties.
 *
 * Each group ships the WHOLE objects verbatim (carrier rule — no hand-picking): {pluginId (plugin id,
 * delete-form hidden id), properties (PluginProperties->toArray()), tokens (UserPluginPaymentTokenCollection
 * ->toArray() = {module, items:[{token, data:{identifier,cardNumber,expiryDate}}], pagination, …})}.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\PaymentCards
 */
class PaymentCardsService implements WidgetDataService {

    private const TOKENS = 'mffPcTokens';
    private const PROPS  = 'mffPcProps';

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $pluginService = Loader::service(Services::PLUGIN);
        $params        = new PluginConnectorTypeParametersGroup();
        $params->setConnectorType(PluginConnectorType::PAYMENT_SYSTEM);
        $params->setNavigationHash(Session::getInstance()->getNavigationHash());
        $plugins = $pluginService->getPlugins($params);
        if (!$plugins instanceof ElementCollection) {
            return ['groups' => []];
        }

        $requests = new BatchRequests();
        foreach ($plugins->getItems() as $plugin) {
            $pluginService->addGetUserPluginPaymentTokens($requests, self::TOKENS . '_' . $plugin->getId(), $plugin->getId());
            $pluginService->addGetPluginProperties($requests, self::PROPS . '_' . $plugin->getId(), $plugin->getId());
        }
        $results = BatchService::getInstance()->send($requests);

        $groups = [];
        foreach ($plugins->getItems() as $plugin) {
            $tokens = $results[self::TOKENS . '_' . $plugin->getId()] ?? null;
            $props  = $results[self::PROPS . '_' . $plugin->getId()] ?? null;
            if (!$tokens instanceof ElementCollection) {
                continue;
            }
            // Ship every plugin's group verbatim, empty cards included — hiding a plugin with no stored
            // cards is a DISPLAY decision the widget owns (`{% if g.tokens.items %}`), not the carrier's.
            $groups[] = [
                'plugin'     => $plugin->toArray(),
                'properties' => $props instanceof PluginProperties ? $props->toArray() : [],
                'tokens'     => $tokens->toArray(),
            ];
        }
        return ['groups' => $groups];
    }
}
