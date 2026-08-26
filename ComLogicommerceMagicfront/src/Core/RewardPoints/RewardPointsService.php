<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\RewardPoints;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\ElementCollection;
use SDK\Services\Parameters\Groups\User\RewardPointsParametersGroup;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * Reward-points data CARRIER for the `rewardPoints` widget. Pure transport: fetches the account's
 * reward-point balances ({@see \SDK\Services\UserService::getRewardPoints}) and hands the COMPLETE raw
 * collection to the widget — `collection->toArray()` verbatim, no field-picking, no reshaping. The widget
 * owns every display rule (the FWK redeemRewardPoints core-branch markup, the computed
 * `available = Σ availables.value`, label placeholder replacement, distribution detail, empty state); see
 * {@see \FWK\ViewHelpers\User\Macro\RedeemRewardPoints} + the store redeemRewardPoints macro.
 *
 * `items` is the ElementCollection::toArray()['items'] — each carries language.{name,description},
 * earned/redeemed/pending and availables[].{value, expirationDate}. `available` is NOT in the API (the FWK
 * ViewHelper derives it) so the widget sums it.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\RewardPoints
 */
class RewardPointsService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $collection = Loader::service(Services::USER)->getRewardPoints(new RewardPointsParametersGroup());
        $items      = $collection instanceof ElementCollection ? $collection->toArray()['items'] : [];
        return ['items' => $items];
    }
}
