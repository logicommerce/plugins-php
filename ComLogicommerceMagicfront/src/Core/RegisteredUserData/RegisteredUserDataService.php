<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\RegisteredUserData;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Session;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use SDK\Core\Dtos\Element;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;

/**
 * "Mis datos para esta cuenta" data CARRIER for the `registeredUserData` widget. Mirrors
 * {@see \FWK\Controllers\Account\AccountRegisteredUserController}: fetches the current account's own
 * registered-user record via {@see \FWK\Services\AccountService::getRegisteredUsersWithRegisteredId}
 * (Resource ACCOUNTS_REGISTERED_USERS_WITH_REGISTERED_ID, deserialised as a **MasterVal/EmployeeVal**),
 * keyed by the session account id + registered-user id — and hands the COMPLETE raw object to the
 * widget (`toArray()` verbatim, no reshaping). The widget owns the store-replica display + the
 * LC-owned accountRegisteredUserUpdateForm.
 *
 * `data` = MasterVal/EmployeeVal::toArray() — `account`(name/id), `registeredUser`
 * (firstName/lastName/email/username/pId/lastUsed/dateAdded), `accountAlias`, `master`(bool),
 * `status`, `defaultLanguageCode`, `defaultCurrencyCode`, and (EmployeeVal) `job` + `role`(id/name).
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\RegisteredUserData
 */
class RegisteredUserDataService implements WidgetDataService {

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $basket  = Session::getInstance()->getBasket();
        $account = $basket->getAccount();
        $ru      = $basket->getRegisteredUser();
        if ($account === null || $ru === null) {
            return ['data' => []];
        }
        $result = Loader::service(Services::ACCOUNT)->getRegisteredUsersWithRegisteredId((string) $account->getId(), $ru->getId());
        return ['data' => $result instanceof Element ? $result->toArray() : []];
    }
}
