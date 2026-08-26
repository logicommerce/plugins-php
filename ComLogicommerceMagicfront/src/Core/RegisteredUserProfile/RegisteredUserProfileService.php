<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\RegisteredUserProfile;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\RoutePaths;
use FWK\Core\Resources\Session;
use FWK\Enums\RouteTypes\InternalPage;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\Element;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Enums\RouteType;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * "Mi perfil" data CARRIER for the `registeredUserProfile` widget. Mirrors the account profile page
 * (user/userForm macro, form `registeredUserForm` → `/lc_ecom_internal/account/update_registered_user`):
 * batch-fetches the account's own registered-user record ({@see \FWK\Services\AccountService::addGetRegisteredUsersMe}
 * → ACCOUNT_REGISTERED_USERS_ME, MasterVal/EmployeeVal) and hands the COMPLETE raw object to the widget.
 * The widget owns the store-replica profile form (gender / firstName / lastName / email(disabled) /
 * username / pId / birthday + LC-owned newsletter toggle + legal agreement); languages/currencies are
 * not needed here. `legal` carries the resolved privacy-policy / terms-of-use URLs (visible href +
 * LC-modal internal url) so the legal-check block renders exactly like the FWK legalCheck macro.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\RegisteredUserProfile
 */
class RegisteredUserProfileService implements WidgetDataService {

    private const KEY = 'mffRegisteredUserProfile';

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $requests = new BatchRequests();
        Loader::service(Services::ACCOUNT)->addGetRegisteredUsersMe($requests, self::KEY);
        $result  = BatchService::getInstance()->send($requests)[self::KEY] ?? null;
        $account = Session::getInstance()->getBasket()->getAccount();
        return [
            'data'      => $result instanceof Element ? $result->toArray() : [],
            'accountId' => $account !== null ? $account->getId() : 0,
            'legal' => [
                'privacyHref'  => RoutePaths::getPath(RouteType::PRIVACY_POLICY),
                'privacyModal' => RoutePaths::getPath(InternalPage::PRIVACY_POLICY),
                'termsHref'    => RoutePaths::getPath(RouteType::TERMS_OF_USE),
                'termsModal'   => RoutePaths::getPath(InternalPage::TERMS_OF_USE),
            ],
        ];
    }
}
