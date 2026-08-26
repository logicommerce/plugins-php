<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Enums;

use FWK\Enums\RouteType;
use SDK\Core\Enums\Enum;

/**
 * Native account/user PAGE route types that the MagicFront account takeover folds into the single
 * account page: when `mff_USER_AREA` is published, each of these routes 302-redirects to
 * `/accounts/used?mfPanel=<section>` (empty section = the landing, no panel). Only display/panel
 * routes are listed — functional flows (oauth callback, email verify, complete-account, lost/anonymous
 * password reset, newsletter unsubscribe) and routes with no MFF panel (policies/wishlist/welcome/…)
 * are intentionally absent so they keep running their native controller.
 *
 * Consumed by {@see \Plugins\ComLogicommerceMagicfront\Core\Controllers\AccountRedirectController}
 * (reads the panel by the request's route type) and by
 * {@see \Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties} (adds these to the
 * override gates when the account page is published).
 *
 * @package Plugins\ComLogicommerceMagicfront\Enums
 */
abstract class AccountRedirectRoutes extends Enum {

    /** Native route type => mfPanel section key ('' = account landing default). */
    public const PANEL = [
        RouteType::USER => '',
        RouteType::USER_ADDRESS_BOOK => 'addresses',
        RouteType::ACCOUNT_ADDRESSES => 'addresses',
        RouteType::USER_ADDRESS_BOOK_ADD => 'addresses',
        RouteType::ACCOUNT_ADDRESS_CREATE => 'addresses',
        RouteType::USER_ADDRESS_BOOK_EDIT => 'addresses',
        RouteType::ACCOUNT_ADDRESS => 'addresses',
        RouteType::USER_CHANGE_PASSWORD => 'changePassword',
        RouteType::REGISTERED_USER_CHANGE_PASSWORD => 'changePassword',
        RouteType::USER_ORDERS => 'orders',
        RouteType::ACCOUNT_ORDERS => 'orders',
        RouteType::USER_ORDER => 'orders',
        RouteType::ACCOUNT_ORDER => 'orders',
        RouteType::USER_RMAS => 'rmas',
        RouteType::ACCOUNT_RMAS => 'rmas',
        RouteType::USER_REWARD_POINTS => 'rewardPoints',
        RouteType::ACCOUNT_REWARD_POINTS => 'rewardPoints',
        RouteType::USER_VOUCHER_CODES => 'voucherCodes',
        RouteType::ACCOUNT_VOUCHER_CODES => 'voucherCodes',
        RouteType::USER_SUBSCRIPTIONS => 'subscriptions',
        RouteType::ACCOUNT_REGISTERED_USER_SUBSCRIPTIONS => 'subscriptions',
        RouteType::USER_STOCK_ALERTS => 'stockAlerts',
        RouteType::ACCOUNT_REGISTERED_USER_STOCK_ALERTS => 'stockAlerts',
        RouteType::USER_PAYMENT_CARDS => 'paymentCards',
        RouteType::ACCOUNT_REGISTERED_USER_PAYMENT_CARDS => 'paymentCards',
        RouteType::USER_SHOPPING_LISTS => 'shoppingLists',
        RouteType::ACCOUNT_REGISTERED_USER_SHOPPING_LISTS => 'shoppingLists',
        RouteType::USER_DELETE_ACCOUNT => 'deleteAccount',
        RouteType::ACCOUNT_DELETE => 'deleteAccount',
        RouteType::USER_SALES_AGENT => 'salesAgent',
        RouteType::REGISTERED_USER_SALES_AGENT => 'salesAgent',
        RouteType::USER_SALES_AGENT_CUSTOMERS => 'salesAgentCustomers',
        RouteType::REGISTERED_USER_SALES_AGENT_CUSTOMERS => 'salesAgentCustomers',
        RouteType::USER_SALES_AGENT_SALES => 'salesAgentSales',
        RouteType::REGISTERED_USER_SALES_AGENT_SALES => 'salesAgentSales',
        RouteType::REGISTERED_USER => 'editDataUser',
        RouteType::USER_CREATE_ACCOUNT => 'register',
        RouteType::ACCOUNT_CREATE => 'register',
    ];

    /** @return string[] the route types folded into the MFF account page. */
    public static function types(): array {
        return array_keys(self::PANEL);
    }
}
