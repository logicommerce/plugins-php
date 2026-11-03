<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Providers;

use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\PageRelationResolver;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Services\Parameters\Groups\User\UserCustomTagsParametersGroup;

/**
 * ACCOUNT-typed custom tag DEFINITIONS for any widget that renders an account form — register,
 * account edit, the checkout user form — attached as `page.customTags.account`.
 *
 * Not in `shared`: only form widgets consume it, and `shared` is reserved for platform-neutral env
 * data any widget may need. Bound to neither route NOR widget type: it fetches when a template in
 * the current render actually reads `page.customTags.account`, so the widget declares the dependency
 * itself and the data follows it to whatever page it is placed on.
 *
 * Named after the payload, not after fwk's `userCustomTags` controller key: the parameters group
 * {@see UserCustomTagsParametersGroup} pins `CustomTagType::ACCOUNT`. The platform also has
 * CATEGORY / PAGE / PRODUCT / ORDER / REGISTERED_USER tags, and widgets already read the PRODUCT
 * ones as `page.*.customTags`.
 *
 * Deliberately NOT gated on a logged-in account (unlike {@see DataWidgetRegistry}'s account widgets):
 * the register form and anonymous checkout need the definitions precisely when there is no session.
 * Nor on the editor canvas: these are the store's tag DEFINITIONS, not shopper data, so the canvas shows the
 * store's real tags.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Providers
 */
class AccountCustomTagsProvider implements WidgetDataProvider {

    /** The `page.*` key this provider fills; also the marker it looks for in the render's templates. */
    private const PAGE_KEY = 'page.customTags.account';

    /**
     * No fixed widget-type list: the provider serves whatever templates READ its key.
     *
     * @return string[]
     */
    public function handledTypes(): array {
        return [];
    }

    /**
     * Runs when any template in this render references {@see self::PAGE_KEY} — a widget declares the
     * dependency simply by reading it in its own Twig. Registering widget types here instead would
     * mean editing this class every time a form widget is added, renamed, or starts/stops using the
     * tags, and a forgotten entry fails SILENTLY (the key is just empty).
     */
    public function appliesTo(ProviderContext $ctx): bool {
        return $ctx->templatesReference(self::PAGE_KEY);
    }

    public function addBatch(BatchRequests $requests, ProviderContext $ctx): void {
    }

    public function attach(?ElementCollection $pages, ProviderContext $ctx): void {
        if ($pages === null) {
            return;
        }
        PageRelationResolver::attachWidgetData($pages, ['setCustomTags' => ['account' => $this->fetch()]]);
    }

    /**
     * @return array
     */
    public function sharedData(ProviderContext $ctx): array {
        return [];
    }

    /**
     * @return array
     */
    private function fetch(): array {
        $collection = Loader::service(Services::USER)->getCustomTags(new UserCustomTagsParametersGroup());
        return $collection instanceof ElementCollection ? $collection->toArray() : [];
    }
}
