<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits;

use FWK\Services\Dtos\BundleDefinitionsWithGroupings;
use SDK\Core\Dtos\ElementCollection;
use SDK\Dtos\Catalog\Product\Product;

/**
 * Mixes Magicfront-specific fields (moduleSettings, draftId, slotId, slot
 * permissions, related collections) into the plugin Page DTO. See
 * Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Dtos\Traits
 */
trait MagicfrontPageTrait {

    protected array $moduleSettings = [];

    protected ?ElementCollection $products = null;

    /**
     * The single product of a product-detail (ficha) route, attached to EVERY
     * widget page on that route so the product-detail widgets read it as
     * `page.product` (the raw SDK Product DTO) — the singular analogue of
     * `products` (category/related LISTS). Null on non-product routes and in
     * the editor/docker preview → widgets render their inline mock.
     */
    protected ?Product $product = null;

    protected ?ElementCollection $categories = null;

    /**
     * The logged-in account view-model for the account route, attached to EVERY widget page so the
     * accountPage widget reads it as `page.account` ({account:{...}, user:{...}, billingAddresses:[],
     * shippingAddresses:[]}) — the account analogue of `product`/`category`. Empty off the account
     * route / anonymous / in the editor preview → the widget renders its inline mock.
     */
    protected array $account = [];

    /**
     * Per-widget account form bundle attached to each account form widget page (accountRegister /
     * accountEdit), read by the widget templateHtml as `page.accountForm` to render the real FWK
     * form on the storefront. Empty off the account route / in the editor preview.
     */
    protected array $accountForm = [];
    /** Labels/constants the productBundles widget needs for option selectors (Sí/No, upload, date pattern). */
    protected array $bundleLabels = [];

    /** Full product JSON (FWK ProductJsonData shape) for the productAddToCart widget's buyForm data-product. */
    protected array $productJson = [];

    /** Login-aware wishlist state for the productAddToCart button ({isLogged, ids, labelAdd, labelDelete}). */
    protected array $wishlist = [];

    /** Native comment-form wiring for the productComments widget ({action, canComment}). */
    protected array $commentForm = [];

    /** The routed product's approved comments (SDK Comment[]) for the productComments widget list. */
    protected array $comments = [];

    /** The logged-in account's serialized order history for the orders widget (`page.orders`), or [] otherwise. */
    protected array $orders = [];

    /** The orders widget raw pagination (`page.ordersPagination` = {page, totalPages}), or [] otherwise. */
    protected array $ordersPagination = [];

    protected array $ordersAccountNames = [];

    /** The raw shopping-list rows collection for the shoppingList widget (`page.shoppingListRows` = {items, products, bundles, pagination}), or [] otherwise. */
    protected array $shoppingListRows = [];

    /** The account's raw shopping lists for the shoppingList widget (`page.shoppingLists` = [{id, name, defaultOne, …}, …]), or [] otherwise. */
    protected array $shoppingLists = [];

    /** The account's raw reward-point balances for the rewardPoints widget (`page.rewardPoints` = {items:[{language, earned, redeemed, pending, availables}, …]}), or [] otherwise. */
    protected array $rewardPoints = [];

    /** The account's raw RMA list for the rmas widget (`page.rmas` = {items:[{id, documentNumber, date, status, substatus, returns}, …]}), or [] otherwise. */
    protected array $rmas = [];

    /** The account's raw stock-alert subscriptions for the stockAlerts widget (`page.stockAlerts` = {items:[{id, email, subscriptionDate, product}, …]}), or [] otherwise. */
    protected array $stockAlerts = [];

    /** The account's raw subscriptions for the subscriptions widget (`page.subscriptions` = {items:[{email, subscriptionType, verified, active, subscriptionDate}, …]}), or [] otherwise. */
    protected array $subscriptions = [];

    /** The account's FRESH invoicing addresses for the addressBook widget (`page.invoicingAddresses`), fetched per render so set-default/add/edit/delete reflect on refresh. */
    protected array $invoicingAddresses = [];

    /** The account's FRESH shipping addresses for the addressBook widget (`page.shippingAddresses`). */
    protected array $shippingAddresses = [];

    /** The account's payment cards grouped by plugin for the paymentCards widget (`page.paymentCards` = {groups:[{pluginId, module, tokens:[…]}, …]}), or [] otherwise. */
    protected array $paymentCards = [];

    /** Sales-agent customer list for the salesAgentCustomers widget (`page.salesAgentCustomers` = {items, pagination, salesAgentId, request}), or [] otherwise. */
    protected array $salesAgentCustomers = [];

    /** Sales-agent sales list + summary for the salesAgentSales widget (`page.salesAgentSales` = {items, totals, pagination, request}), or [] otherwise. */
    protected array $salesAgentSales = [];

    /** The account's redeemable vouchers for the voucherCodes widget (`page.voucherCodes` = {items:[{code, availableBalance, expirationDate}, …]}), or [] otherwise. */
    protected array $voucherCodes = [];

    protected array $checkout = [];

    /** The account's own registered-user record for the registeredUserData widget (`page.registeredUserData` = {data:{account, registeredUser, accountAlias, master, status, job, role, …}}), or [] otherwise. */
    protected array $registeredUserData = [];

    /** The account's own registered-user profile for the registeredUserProfile widget (`page.registeredUserProfile` = {data:{registeredUser:{gender, firstName, …, birthday}, account}, legal:{privacyHref, …}}), or [] otherwise. */
    protected array $registeredUserProfile = [];

    /**
     * The product-detail route's bundle definitions (the singular analogue of `product`), attached
     * to EVERY widget page so the productBundles widget reads it as `page.productBundles`. Null off
     * a product route / in the editor preview → the widget renders its inline mock.
     */
    protected ?BundleDefinitionsWithGroupings $productBundles = null;

    /** Related-products lists keyed by an opaque group id (LC uses the position as key), read by the
     *  productList widget as `page.productRelated[<id>]`. Lets several productList instances on one
     *  page each render a different related group by setting a different id. */
    protected array $productRelated = [];

    /** Single route item — the current blog post / blog category (page.post / page.blogCategory). */
    protected mixed $post = null;

    protected mixed $blogCategory = null;

    /** Single route item — the current blog tag (page.blogTag, BLOG_TAG route). */
    protected mixed $blogTag = null;

    /** Single route item — the current blogger/author (page.blogger, BLOG_BLOGGER route). */
    protected mixed $blogger = null;

    /** Commerce blog settings (page.blogSettings — commentsMode / maxIpComments / …). */
    protected mixed $blogSettings = null;

    /** True when the storefront session is a logged user (page.userLogged; gates registered-only comment forms). */
    protected ?bool $userLogged = null;

    /** Blog collections consumed by blog widgets (page.blogPosts / page.blogCategories / page.blogRecentPosts / page.blogTags). */
    protected ?ElementCollection $blogPosts = null;

    protected ?ElementCollection $blogCategories = null;

    protected ?ElementCollection $blogRecentPosts = null;

    protected ?ElementCollection $blogTags = null;

    protected ?ElementCollection $blogComments = null;

    /**
     * Single category exposed as page.category: the raw SDK Category on a catalog category-detail
     * route, or the blog category on a blog route (entityName-bound headings). Null off both / in
     * the editor preview → widgets render their inline mock. Its product LIST goes to page.products.
     */
    protected mixed $category = null;

    /** Ordered breadcrumb crumbs exposed as page.breadcrumb ([{label,url}]; catalog adds `current`). */
    protected array $breadcrumb = [];
    protected string $draftId = "";

    /** Template lookup key (version wire key); empty → {@see getTemplateKey()} falls back to customType. */
    protected string $templateKey = "";

    protected ?string $slotId = null;

    /** Per-widget write revision from the Magic Front API (null = legacy). */
    protected ?int $widgetRevision = null;

    protected ?array $slotPermissions = null;

    /**
     * dcsapi-set flag: true when the widget instance was auto-generated by
     * a parent template's childStructure (e.g. a per-item loop), false for
     * normal user-placed widgets, null when the API omitted it.
     * Used to distinguish slot pseudo-widgets from real widget templates —
     * critical now that merchant-created templates also have UUID ids and
     * the old "id matches UUID" heuristic no longer holds.
     */
    protected ?bool $autoGenerated = null;

    public function getAutoGenerated(): ?bool {
        return $this->autoGenerated;
    }

    /**
     * True for childStructure pseudo-widgets — auto-generated AND occupying
     * no typed slot. Mirrors {@see \Plugins\ComLogicommerceMagicfront\Dtos\Widgets\WidgetInstance::isChildStructurePseudo()}
     * so collectors / CSS scope resolution work uniformly on both DTOs.
     */
    public function isChildStructurePseudo(): bool {
        return $this->autoGenerated === true && ($this->slotId === null || $this->slotId === '');
    }

    /**
     * Devuelve los settings del módulo.
     * Primero intenta usar moduleSettings directo, si está vacío extrae de customTagValues.
     */
    public function getModuleSettings(): array {
        if (count($this->moduleSettings) > 0) {
            return $this->moduleSettings;
        }

        // Fallback: extraer TODOS los customTagValues a moduleSettings
        foreach ($this->customTagValues ?? [] as $tag) {
            $pId = $tag->getCustomTagPId();
            if (!empty($pId)) {
                $this->moduleSettings[$pId] = $tag->getValue();
            }
        }
        return $this->moduleSettings;
    }

    /**
     * Devuelve los settings del módulo (lc-*).
     */
    public function getProducts(): ?ElementCollection {
        return $this->products;
    }

    public function setProducts(?ElementCollection $products): void {
        $this->products = $products;
    }

    /** The route's product-detail product (raw SDK Product), or null off a product route. */
    public function getProduct(): ?Product {
        return $this->product;
    }

    public function setProduct(?Product $product): void {
        $this->product = $product;
    }

    /** The route's breadcrumb trail exposed as page.breadcrumb, or [] off any route. */
    public function getBreadcrumb(): array {
        return $this->breadcrumb;
    }

    public function setBreadcrumb(array $breadcrumb): void {
        $this->breadcrumb = $breadcrumb;
    }

    /** The logged-in account view-model for the accountPage widget (`page.account`), or [] off the account route. */
    public function getAccount(): array {
        return $this->account;
    }

    public function setAccount(array $account): void {
        $this->account = $account;
    }

    /** The per-widget account form bundle for this account form widget (`page.accountForm`), or [] otherwise. */
    public function getAccountForm(): array {
        return $this->accountForm;
    }

    public function setAccountForm(array $accountForm): void {
        $this->accountForm = $accountForm;
    }

    /** Labels/constants for the productBundles option selectors, or [] off a product route. */
    public function getBundleLabels(): array {
        return $this->bundleLabels;
    }

    public function setBundleLabels(array $bundleLabels): void {
        $this->bundleLabels = $bundleLabels;
    }

    /** Full product JSON for the productAddToCart buyForm, or [] off a product route. */
    public function getProductJson(): array {
        return $this->productJson;
    }

    public function setProductJson(array $productJson): void {
        $this->productJson = $productJson;
    }

    /** Login-aware wishlist state for the productAddToCart button, or [] off a product route. */
    public function getWishlist(): array {
        return $this->wishlist;
    }

    public function setWishlist(array $wishlist): void {
        $this->wishlist = $wishlist;
    }

    /** Native comment-form wiring for the productComments widget, or [] off a product route. */
    public function getCommentForm(): array {
        return $this->commentForm;
    }

    public function setCommentForm(array $commentForm): void {
        $this->commentForm = $commentForm;
    }

    /** The routed product's approved comments for the productComments widget, or [] off a product route. */
    public function getComments(): array {
        return $this->comments;
    }

    public function setComments(array $comments): void {
        $this->comments = $comments;
    }

    /** The logged-in account's serialized order history for the orders widget, or [] otherwise. */
    public function getOrders(): array {
        return $this->orders;
    }

    public function setOrders(array $orders): void {
        $this->orders = $orders;
    }

    public function getCheckout(): array {
        return $this->checkout;
    }

    public function setCheckout(array $checkout): void {
        $this->checkout = $checkout;
    }

    public function getInvoicingAddresses(): array {
        return $this->invoicingAddresses;
    }

    public function setInvoicingAddresses(array $invoicingAddresses): void {
        $this->invoicingAddresses = $invoicingAddresses;
    }

    public function getShippingAddresses(): array {
        return $this->shippingAddresses;
    }

    public function setShippingAddresses(array $shippingAddresses): void {
        $this->shippingAddresses = $shippingAddresses;
    }

    /** The orders widget raw pagination ({page, totalPages}), or [] otherwise. */
    public function getOrdersPagination(): array {
        return $this->ordersPagination;
    }

    public function setOrdersPagination(array $ordersPagination): void {
        $this->ordersPagination = $ordersPagination;
    }

    public function getOrdersAccountNames(): array {
        return $this->ordersAccountNames;
    }

    public function setOrdersAccountNames(array $ordersAccountNames): void {
        $this->ordersAccountNames = $ordersAccountNames;
    }

    /** The raw shopping-list rows collection ({items, products, bundles, pagination}) for the shoppingList widget, or [] otherwise. */
    public function getShoppingListRows(): array {
        return $this->shoppingListRows;
    }

    public function setShoppingListRows(array $shoppingListRows): void {
        $this->shoppingListRows = $shoppingListRows;
    }

    /** The account's raw shopping lists ([{id, name, defaultOne, …}, …]) for the shoppingList widget, or [] otherwise. */
    public function getShoppingLists(): array {
        return $this->shoppingLists;
    }

    public function setShoppingLists(array $shoppingLists): void {
        $this->shoppingLists = $shoppingLists;
    }

    /** The account's raw reward-point balances ({items:[…]}) for the rewardPoints widget, or [] otherwise. */
    public function getRewardPoints(): array {
        return $this->rewardPoints;
    }

    public function setRewardPoints(array $rewardPoints): void {
        $this->rewardPoints = $rewardPoints;
    }

    /** The account's raw RMA list ({items:[…]}) for the rmas widget, or [] otherwise. */
    public function getRmas(): array {
        return $this->rmas;
    }

    public function setRmas(array $rmas): void {
        $this->rmas = $rmas;
    }

    /** The account's raw stock-alert subscriptions ({items:[…]}) for the stockAlerts widget, or [] otherwise. */
    public function getStockAlerts(): array {
        return $this->stockAlerts;
    }

    public function setStockAlerts(array $stockAlerts): void {
        $this->stockAlerts = $stockAlerts;
    }

    /** The account's raw subscriptions ({items:[…]}) for the subscriptions widget, or [] otherwise. */
    public function getSubscriptions(): array {
        return $this->subscriptions;
    }

    public function setSubscriptions(array $subscriptions): void {
        $this->subscriptions = $subscriptions;
    }

    /** The account's payment cards grouped by plugin ({groups:[…]}) for the paymentCards widget, or [] otherwise. */
    public function getPaymentCards(): array {
        return $this->paymentCards;
    }

    public function setPaymentCards(array $paymentCards): void {
        $this->paymentCards = $paymentCards;
    }

    /** Sales-agent customer list ({items, pagination, salesAgentId, request}) for the salesAgentCustomers widget, or [] otherwise. */
    public function getSalesAgentCustomers(): array {
        return $this->salesAgentCustomers;
    }

    public function setSalesAgentCustomers(array $salesAgentCustomers): void {
        $this->salesAgentCustomers = $salesAgentCustomers;
    }

    /** Sales-agent sales list + summary ({items, totals, pagination, request}) for the salesAgentSales widget, or [] otherwise. */
    public function getSalesAgentSales(): array {
        return $this->salesAgentSales;
    }

    public function setSalesAgentSales(array $salesAgentSales): void {
        $this->salesAgentSales = $salesAgentSales;
    }

    /** The account's redeemable vouchers ({items:[…]}) for the voucherCodes widget, or [] otherwise. */
    public function getVoucherCodes(): array {
        return $this->voucherCodes;
    }

    public function setVoucherCodes(array $voucherCodes): void {
        $this->voucherCodes = $voucherCodes;
    }

    /** The account's own registered-user record ({data:{…}}) for the registeredUserData widget, or [] otherwise. */
    public function getRegisteredUserData(): array {
        return $this->registeredUserData;
    }

    public function setRegisteredUserData(array $registeredUserData): void {
        $this->registeredUserData = $registeredUserData;
    }

    /** The account's own registered-user profile ({data:{…}, legal:{…}}) for the registeredUserProfile widget, or [] otherwise. */
    public function getRegisteredUserProfile(): array {
        return $this->registeredUserProfile;
    }

    public function setRegisteredUserProfile(array $registeredUserProfile): void {
        $this->registeredUserProfile = $registeredUserProfile;
    }

    /** The route's product bundle definitions, or null off a product route. */
    public function getProductBundles(): ?BundleDefinitionsWithGroupings {
        return $this->productBundles;
    }

    public function setProductBundles(?BundleDefinitionsWithGroupings $productBundles): void {
        $this->productBundles = $productBundles;
    }

    public function getProductRelated(): array {
        return $this->productRelated;
    }

    public function setProductRelated(array $productRelated): void {
        $this->productRelated = $productRelated;
    }

    public function getCategories(): ?ElementCollection {
        return $this->categories;
    }

    public function setCategories(?ElementCollection $categories): void {
        $this->categories = $categories;
    }

    public function getPost(): mixed {
        return $this->post;
    }

    public function setPost(mixed $post): void {
        $this->post = $post;
    }

    public function getBlogCategory(): mixed {
        return $this->blogCategory;
    }

    public function setBlogCategory(mixed $blogCategory): void {
        $this->blogCategory = $blogCategory;
    }

    public function getBlogTag(): mixed {
        return $this->blogTag;
    }

    public function setBlogTag(mixed $blogTag): void {
        $this->blogTag = $blogTag;
    }

    public function getBlogger(): mixed {
        return $this->blogger;
    }

    public function setBlogger(mixed $blogger): void {
        $this->blogger = $blogger;
    }

    public function getBlogSettings(): mixed {
        return $this->blogSettings;
    }

    public function setBlogSettings(mixed $blogSettings): void {
        $this->blogSettings = $blogSettings;
    }

    public function getUserLogged(): ?bool {
        return $this->userLogged;
    }

    public function setUserLogged(?bool $userLogged): void {
        $this->userLogged = $userLogged;
    }

    public function getBlogPosts(): ?ElementCollection {
        return $this->blogPosts;
    }

    public function setBlogPosts(?ElementCollection $blogPosts): void {
        $this->blogPosts = $blogPosts;
    }

    public function getBlogCategories(): ?ElementCollection {
        return $this->blogCategories;
    }

    public function setBlogCategories(?ElementCollection $blogCategories): void {
        $this->blogCategories = $blogCategories;
    }

    public function getBlogRecentPosts(): ?ElementCollection {
        return $this->blogRecentPosts;
    }

    public function setBlogRecentPosts(?ElementCollection $blogRecentPosts): void {
        $this->blogRecentPosts = $blogRecentPosts;
    }

    public function getBlogTags(): ?ElementCollection {
        return $this->blogTags;
    }

    public function setBlogTags(?ElementCollection $blogTags): void {
        $this->blogTags = $blogTags;
    }

    public function getBlogComments(): ?ElementCollection {
        return $this->blogComments;
    }

    public function setBlogComments(?ElementCollection $blogComments): void {
        $this->blogComments = $blogComments;
    }

    public function getCategory(): mixed {
        return $this->category;
    }

    public function setCategory(mixed $category): void {
        $this->category = $category;
    }

    public function setFWKSubpages(array $subpages): void {
        $this->subpages = $subpages;
    }

    /**
     * Override SDK Page::setSubpages so that constructor roundtrips (via toArray →
     * new Page($array), triggered by FillFromParentTrait::fillFromParentCollection
     * in PageRelationResolver) preserve the plugin Page class. The SDK default
     * rehydrates children through PageFactory, which returns SDK Page instances
     * lacking MagicfrontPageTrait — i.e. slotId, slotPermissions, moduleSettings
     * and draftId would silently drop on every roundtrip.
     *
     * Input may be a mix of plugin Pages (already hydrated), arrays (serialized
     * form emerging from toArray), or SDK Pages (upgraded in place). All three
     * are normalized to plugin Page so getSlotId/getSlotPermissions keep working
     * through the whole resolution chain.
     */
    protected function setSubpages(array $subpages): void {
        $items = [];
        $pluginPageClass = \Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page::class;
        foreach ($subpages as $sp) {
            if ($sp instanceof $pluginPageClass) {
                $items[] = $sp;
            } elseif (is_array($sp)) {
                $items[] = new $pluginPageClass($sp);
            } elseif (is_object($sp) && method_exists($sp, 'toArray')) {
                $items[] = new $pluginPageClass($sp->toArray());
            }
        }
        $this->subpages = $items;
    }

    public function setDraftId(string $draftId): void {
        $this->draftId = $draftId;
    }

    public function setWidgetRevision(?int $widgetRevision): void {
        $this->widgetRevision = $widgetRevision;
    }

    public function getWidgetRevision(): ?int {
        return $this->widgetRevision;
    }

    public function getDraftId(): string {
        return $this->draftId;
    }

    public function setTemplateKey(string $templateKey): void {
        $this->templateKey = $templateKey;
    }

    public function getTemplateKey(): string {
        return $this->templateKey !== '' ? $this->templateKey : $this->getCustomType();
    }

    public function setSlotId(?string $slotId): void {
        $this->slotId = $slotId;
    }

    public function getSlotId(): ?string {
        return $this->slotId;
    }

    public function setSlotPermissions(?array $permissions): void {
        $this->slotPermissions = $permissions;
    }

    public function getSlotPermissions(): ?array {
        return $this->slotPermissions;
    }
}
