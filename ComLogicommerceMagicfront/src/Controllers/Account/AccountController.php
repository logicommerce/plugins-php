<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Controllers\Account;

use FWK\Controllers\Account\AccountController as FWKAccountController;
use Plugins\ComLogicommerceMagicfront\Core\Controllers\Traits\MagicfrontTrait;
use Plugins\ComLogicommerceMagicfront\Core\Resources\BundleOptionsResolver;
use SDK\Core\Resources\BatchRequests;
use SDK\Dtos\Common\Route;

/**
 * Plugin override for the account area (RouteType::ACCOUNT).
 *
 * The store runs usedAccountPath=true, so the account landing routes as ACCOUNT and is served by
 * FWK {@see FWKAccountController}; this plugin subclass takes it over when the plugin claims ACCOUNT
 * in its `availablepages` (see {@see \Plugins\ComLogicommerceMagicfront\Enums\AvailablePagesValue::ACCOUNT}).
 *
 * MagicFront flow (Motor A), exactly like ProductController/CategoryController: the singleton
 * mff_USER_AREA page is the template painted for the whole account area, resolved generically by
 * {@see MagicfrontTrait::magicfrontPage()} (SpecialPagePId::ACCOUNT); FWK's base keeps the real
 * account/session view-model in `controllerItem`. When the page is not published the takeover gate
 * ({@see \Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties::getControllerOverridePages()})
 * keeps producción on the commerce's own account controller.
 *
 * @see FWKAccountController
 * @see \Plugins\ComLogicommerceMagicfront\Controllers\Product\ProductController
 *
 * @package Plugins\ComLogicommerceMagicfront\Controllers\Account
 */
class AccountController extends FWKAccountController {

    use MagicfrontTrait;

    public function __construct(Route $route) {
        parent::__construct($route);
        $this->magicfrontInit($route);
    }

    protected function setBatchData(BatchRequests $requests): void {
        parent::setBatchData($requests);
        $this->setMagicfrontBatchData($requests);
    }

    protected function setData(array $additionalData = []): void {
        parent::setData($additionalData);
        $this->setMagicfrontData();
    }

    /**
     * Account-area widgets that embed a buyProductForm (e.g. the shoppingList/Favoritos rows) need the
     * same option labels/constants the product page uses — `page.bundleLabels` (attachMaxSize, upload/
     * date/boolean labels). Mirror ProductController: build them from the language sheet so the buy-form
     * option renderers (ATTACHMENT max size, DATE picker, …) work in the account area too.
     */
    protected function routeBundleLabels(): array {
        return BundleOptionsResolver::build($this->getLanguageSheet(), $this->getRoute());
    }
}
