<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Account;

use Plugins\ComLogicommerceMagicfront\Core\Controllers\AccountRedirectController;

/**
 * Account-area takeover redirect: when the MagicFront account page (mff_USER_AREA) is
 * published, this native account/user route is folded into the single MFF account page
 * (/accounts/used?mfPanel=...) — see {@see AccountRedirectController}.
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Account
 */
class OrdersController extends AccountRedirectController {
}
