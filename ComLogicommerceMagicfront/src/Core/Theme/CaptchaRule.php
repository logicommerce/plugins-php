<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Theme;

use FWK\Core\Resources\Loader;
use FWK\Core\Theme\Dtos\Configuration;
use FWK\Core\Theme\Dtos\FormUseCaptcha;
use FWK\Core\Theme\Dtos\Forms;
use FWK\Enums\RouteTypes\InternalAccount;
use FWK\Enums\RouteTypes\InternalBlog;
use FWK\Enums\RouteTypes\InternalPage;
use FWK\Enums\RouteTypes\InternalProduct;
use FWK\Enums\RouteTypes\InternalResources;
use FWK\Enums\RouteTypes\InternalUser;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Core\Services\RouteResolver;
use Plugins\ComLogicommerceMagicfront\Core\Services\WidgetToPageTransformer;
use Plugins\ComLogicommerceMagicfront\Core\Twig\PublishedChrome;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Enums\ChromeKind;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Dtos\Catalog\Page\Page as SdkPage;
use SDK\Dtos\Common\Route;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * A MagicFront form asks for the captcha when ITS widget says so, never the theme: on the submit of a form LogiCommerce
 * protects, the theme configuration's `forms.useCaptcha.<form>` is set from the `captcha` setting of the widget that
 * sent it, read from what is PUBLISHED (the page the form was on, or the published header, footer or panels), so the
 * browser cannot switch it off. FWK then adds or leaves out the token field it checks (Form::addCaptcha, CheckCaptcha).
 *
 * The form names its widget in two hidden fields: {@see self::WIDGET_FIELD} (its id) and {@see self::PAGE_FIELD} (the
 * page it was painted on, `mff_pagePId`, or else the Referer). A submit without them is not one of ours (a theme form) and keeps the theme's
 * setting. A widget that names itself but is not found published, or has no `captcha` saved, asks for the captcha: its
 * setting is on when it is born.
 *
 * Still needs the store's captcha plugin: without it FWK adds no token field whatever the setting says.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Theme
 */
final class CaptchaRule {

    public const WIDGET_FIELD = 'mffCaptchaWidget';

    public const PAGE_FIELD = 'mffCaptchaPage';

    /** The widget setting ({@see \Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page::getModuleSettings()}). */
    public const SETTING = 'captcha';

    private const LOOKUP_KEY = 'mffCaptchaRuleLookup';

    /** Each submit route of a form LogiCommerce can protect, and its key in `forms.useCaptcha`. */
    public const FORM_BY_ROUTE = [
        InternalUser::LOGIN => FormUseCaptcha::LOGIN,
        InternalUser::LOST_PASSWORD => FormUseCaptcha::LOST_PASSWORD,
        InternalUser::NEW_PASSWORD => FormUseCaptcha::NEW_PASSWORD,
        InternalUser::UPDATE_PASSWORD => FormUseCaptcha::UPDATE_PASSWORD,
        InternalUser::DELETE_ACCOUNT => FormUseCaptcha::DELETE_ACCOUNT,
        InternalUser::DELETE_PAYMENT_CARD => FormUseCaptcha::DELETE_PAYMENT_CARD,
        InternalUser::ADD_USER => FormUseCaptcha::SET_USER,
        InternalUser::ADD_USER_FAST_REGISTER => FormUseCaptcha::SET_USER,
        InternalUser::SET_ADDRESS_BOOK => FormUseCaptcha::SET_USER,
        InternalUser::RETURN_REQUEST => FormUseCaptcha::RETURN_REQUEST,
        InternalUser::SET_SHOPPING_LIST => FormUseCaptcha::SHOPPING_LIST,
        InternalUser::SET_SHOPPING_LIST_ROW => FormUseCaptcha::SHOPPING_LIST_ROW_NOTES,
        InternalUser::SEND_SHOPPING_LIST_ROWS => FormUseCaptcha::SEND_SHOPPING_LIST_ROWS,
        InternalUser::DELETE_SHOPPING_LIST_ROWS => FormUseCaptcha::DELETE_SHOPPING_LIST_ROWS,
        InternalProduct::ADD_COMMENT => FormUseCaptcha::COMMENT,
        InternalProduct::SET_CONTACT => FormUseCaptcha::PRODUCT_CONTACT,
        InternalProduct::SET_RECOMMEND => FormUseCaptcha::RECOMMEND,
        InternalProduct::SUBSCRIBE_STOCK => FormUseCaptcha::STOCK_ALERT,
        InternalBlog::ADD_COMMENT => FormUseCaptcha::COMMENT,
        InternalBlog::SUBSCRIBE => FormUseCaptcha::BLOG_SUBSCRIBE,
        InternalPage::SEND_CONTACT => FormUseCaptcha::CONTACT,
        InternalResources::SEND_MAIL => FormUseCaptcha::SEND_MAIL,
        InternalAccount::UPDATE_REGISTERED_USER => FormUseCaptcha::SET_REGISTERED_USER,
        InternalAccount::CREATE_ACCOUNT_REGISTERED_USER => FormUseCaptcha::ACCOUNT_REGISTERED_USER_CREATE,
        InternalAccount::APPROVE_ACCOUNT_REGISTERED_USER => FormUseCaptcha::ACCOUNT_REGISTERED_USER_APPROVE,
        InternalAccount::MOVE_ACCOUNT_REGISTERED_USER => FormUseCaptcha::ACCOUNT_REGISTERED_USER_MOVE,
    ];

    /**
     * The routes the rule acts on, for PluginProperties::getTcOverridePages().
     *
     * @return string[]
     */
    public static function routes(): array {
        return array_keys(self::FORM_BY_ROUTE);
    }

    /**
     * The configuration data with `forms.useCaptcha.<form>` set from the widget that sent this submit, or untouched when
     * the route is not a protected form or the submit is not from a MagicFront widget.
     *
     * @param array $configurationData
     * @param string $routeType
     * @return array
     */
    public static function apply(array $configurationData, string $routeType): array {
        $widgetId = RequestQuery::postedValue(self::WIDGET_FIELD, RequestQuery::matching('/^[A-Za-z0-9_-]{1,64}$/'));
        if ($widgetId === null) {
            return $configurationData;
        }
        $pagePId = RequestQuery::postedValue(self::PAGE_FIELD, RequestQuery::matching('/^[A-Za-z0-9_.-]{1,128}$/')) ?? self::refererPId();
        return self::withWidgetSetting($configurationData, $routeType, self::findPublished($pagePId, $widgetId)?->getModuleSettings());
    }

    /**
     * The configuration data with `forms.useCaptcha.<form>` set from the settings of the widget (null = not found
     * published: on).
     *
     * @param array $configurationData
     * @param string $routeType
     * @param array|NULL $widgetSettings
     * @return array
     */
    public static function withWidgetSetting(array $configurationData, string $routeType, ?array $widgetSettings): array {
        $form = self::FORM_BY_ROUTE[$routeType] ?? null;
        if ($form === null) {
            return $configurationData;
        }
        $configurationData[Configuration::FORMS][Forms::USE_CAPTCHA][$form] = self::isOn($widgetSettings ?? []);
        return $configurationData;
    }

    /**
     * Whether the widget asks for the captcha: on unless it has it saved switched off.
     *
     * @param array $settings
     * @return bool
     */
    public static function isOn(array $settings): bool {
        if (!array_key_exists(self::SETTING, $settings)) {
            return true;
        }
        return !in_array($settings[self::SETTING], [false, 'false', 0, '0', ''], true);
    }

    /**
     * The widget among `$pages` whose id (or draft id, as the widgets macro paints it) is `$widgetId`.
     *
     * @param ElementCollection|NULL $pages
     * @param string $widgetId
     * @return PluginPage|NULL
     */
    public static function findIn(?ElementCollection $pages, string $widgetId): ?PluginPage {
        $stack = $pages?->getItems() ?? [];
        while ($stack !== []) {
            $page = array_shift($stack);
            if (!$page instanceof PluginPage) {
                continue;
            }
            if ((string) $page->getId() === $widgetId || (string) $page->getDraftId() === $widgetId) {
                return $page;
            }
            array_push($stack, ...($page->getSubpages() ?? []));
        }
        return null;
    }

    /** The published widget: on the page the form was painted on, else in the published header, footer or panels. */
    private static function findPublished(string $pagePId, string $widgetId): ?PluginPage {
        if ($pagePId !== '') {
            $widget = self::findIn(self::loadPage($pagePId)?->toPages(), $widgetId);
            if ($widget !== null) {
                return $widget;
            }
        }
        $chrome = new PublishedChrome(null);
        foreach (ChromeKind::cases() as $kind) {
            $document = $chrome->getDocument($kind);
            if ($document === null) {
                continue;
            }
            $widget = self::findIn(WidgetToPageTransformer::transform($document->widgetsFor($kind)), $widgetId);
            if ($widget !== null) {
                return $widget;
            }
        }
        return null;
    }

    /**
     * The page the form was sent from, recovered from the Referer as WidgetContentHandler does: the store paints the
     * widgets in FWK's core Twig, which never gets the `mff_pagePId` global, so the field usually comes empty.
     */
    private static function refererPId(): string {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $route = $referer === '' ? null : RouteResolver::forPath($referer);
        if (!$route instanceof Route) {
            return '';
        }
        return SpecialPagePId::forRouteType((string) $route->getType()) ?? (string) $route->getId();
    }

    /** The published document of a page, by its pId or numeric id, as WidgetContentHandler reads it. */
    private static function loadPage(string $pId): ?PageDocument {
        $requests = new BatchRequests();
        if (ctype_digit($pId)) {
            Loader::service(Services::PAGE)->addGetPage($requests, self::LOOKUP_KEY, (int) $pId);
        } else {
            $params = new PageParametersGroup();
            $params->setPId($pId);
            Loader::service(Services::PAGE)->addGetPages($requests, self::LOOKUP_KEY, $params);
        }
        $result = BatchService::getInstance()->send($requests);
        $response = $result[self::LOOKUP_KEY] ?? null;
        $page = $response instanceof ElementCollection ? ($response->getItems()[0] ?? null) : $response;
        if (!$page instanceof SdkPage) {
            return null;
        }
        return PageDocument::fromJson($page->getLanguage()?->getPageContent());
    }
}
