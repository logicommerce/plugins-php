<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Subscriptions;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Subscriptions data CARRIER for the `subscriptions` widget. Pure transport: fetches the account's
 * subscriptions ({@see \SDK\Services\UserService::getSubscriptions} → ACCOUNTS_REGISTERED_USERS_SUBSCRIPTIONS,
 * NOT paginated) and hands the COMPLETE raw items to the widget — `toArray()['items']` verbatim. The widget
 * owns display (type/status label maps computed from subscriptionType + verified/active booleans, the
 * FWK subscriptions macro); see fwk/themes/core/macros/modes/bootstrap5/user/subscriptions.html.twig.
 *
 * `items` are `Subscription::toArray()` — each carries email, subscriptionType (STOCK_ALERT|BLOG),
 * verified, active, subscriptionDate and optional unsubscribedAt.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Subscriptions
 */
class SubscriptionsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $collection = Loader::service(Services::USER)->getSubscriptions();
        $items      = $collection instanceof ElementCollection ? $collection->toArray()['items'] : [];
        return ['items' => $items];
    }
}
