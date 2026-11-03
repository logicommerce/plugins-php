<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Theme;

use FWK\Core\Resources\Loader;
use FWK\Core\Theme\Dtos\Account;
use FWK\Core\Theme\Dtos\Commerce;
use FWK\Core\Theme\Dtos\FormAccount;
use FWK\Core\Theme\Dtos\FormAccountFields;
use FWK\Core\Theme\Dtos\Configuration;
use FWK\Core\Theme\Dtos\FormField;
use FWK\Core\Theme\Dtos\FormFieldsSetUser;
use FWK\Core\Theme\Dtos\FormMaster;
use FWK\Core\Theme\Dtos\FormRegisteredUser;
use FWK\Core\Theme\Dtos\FormRegisteredUserFields;
use FWK\Core\Theme\Dtos\Forms;
use FWK\Core\Theme\Dtos\FormSetUser;
use FWK\Core\Theme\Dtos\FormSetUserFields;
use FWK\Core\Theme\Dtos\FormSetUserFieldsByUserType;
use FWK\Core\Theme\Dtos\FormSetUserFieldsByUserTypeElement;
use FWK\Core\Theme\PluginTcModifier;
use FWK\Enums\RouteType;
use FWK\Enums\RouteTypes\InternalAccount;
use FWK\Enums\RouteTypes\InternalCheckout;
use FWK\Enums\RouteTypes\InternalUser;
use FWK\Enums\Services;
use FWK\Enums\SetUserTypeForms;
use Plugins\ComLogicommerceMagicfront\Core\Checkout\CheckoutRoute;
use Plugins\ComLogicommerceMagicfront\Core\Resources\RequestQuery;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties;
use Plugins\ComLogicommerceMagicfront\Dtos\Content\PageDocument;
use Plugins\ComLogicommerceMagicfront\Enums\SpecialPagePId;
use SDK\Core\Dtos\ElementCollection;
use SDK\Dtos\Catalog\Page\Page as SdkPage;
use SDK\Enums\GeneralRestriction;
use SDK\Enums\UserType;
use SDK\Services\Parameters\Groups\PageParametersGroup;

/**
 * Adjusts the commerce Tc on the routes {@see \Plugins\ComLogicommerceMagicfront\Dtos\Common\PluginProperties::getTcOverridePages()}
 * lists (see {@see PluginTcModifier}); each rule reads the PUBLISHED page, so an unpublished one changes nothing.
 *
 * The MagicFront checkout customer form is composed from widgets, so the merchant decides in the editor
 * which standard user fields it collects, not the theme. On the form submit (the step submit, or the one step
 * checkout recalculate and end order, which save the customer the same way) the fields the PUBLISHED
 * checkout page actually collects (its checkoutFormField fieldKeys, plus gender when it holds a
 * checkoutGenderField and customTags when it holds a checkoutCustomTags) are therefore included (not required: the widget validates what the merchant
 * marked), so FWK's own whitelist lets them through. Fields the page does not place stay as the theme
 * says, and the fields the theme already includes keep their settings.
 *
 * The address book modal of that page is composed the same way (addressSection content slot: checkoutUserTypes
 * tabs by section billing/shipping holding checkoutFormField nodes), so on the address book submit the address
 * fields it places are included too, per billing user type and for shipping, limited to what an address stores
 * (BaseUserDataParametersGroup + alias); a key an address cannot hold (birthday, email...) is never added.
 *
 * The account area widgets decide their fields the same way, from their own settings: on the register submit the
 * fields each included user type of the published mff_USER_AREA accountRegister turns on, on the account edit
 * submit the account (af_*) and registered user (ruf_*) fields accountEdit turns on, and on the registered user
 * update the fields registeredUserProfile always renders, are included too.
 *
 * The account type to create (Tc Account::ACCOUNT_TYPE): when the form is composed with checkoutUserTypes tabs (the
 * register submit of mff_USER_AREA, the checkout customer submit of mff_CHECKOUT), the tab the customer submitted
 * decides it — the «General» tab posts mffGeneralAccount and creates a GENERAL account, any other tab an account of
 * its user type (NON_GENERAL). A built-in accountRegister keeps its generalRestriction setting; without a single
 * valid value there, the theme's own stays.
 *
 * The MagicFront checkout is one route whichever flow its widgets paint, so while mff_CHECKOUT is published every
 * checkout route runs as the commerce's one step checkout does (Commerce::USE_ONE_STEP_CHECKOUT): the native step
 * routes fold into it and the denied order, express checkout and minimum amount redirects land on it. The basket
 * step is left alone while the basket page (mff_BASKET) is published: that page is what it paints.
 *
 * The captcha of the forms LogiCommerce protects is the widget's that sent the form, never the theme's
 * ({@see CaptchaRule}), on top of every rule above.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Theme
 */
class TcModifier implements PluginTcModifier {

    private const GENERAL_TAB = 'GENERAL';

    private const GENERAL_ACCOUNT_PARAM = 'mffGeneralAccount';

    private const CHECKOUT_USER_FIELDS = [
        FormFieldsSetUser::ADDRESS,
        FormFieldsSetUser::ADDRESS_ADDITIONAL_INFORMATION,
        FormFieldsSetUser::BIRTHDAY,
        FormFieldsSetUser::COMPANY,
        FormFieldsSetUser::CUSTOM_TAGS,
        FormFieldsSetUser::EMAIL,
        FormFieldsSetUser::FAX,
        FormFieldsSetUser::FIRST_NAME,
        FormFieldsSetUser::GENDER,
        FormFieldsSetUser::IMAGE,
        FormFieldsSetUser::LAST_NAME,
        FormFieldsSetUser::MOBILE,
        FormFieldsSetUser::NICK,
        FormFieldsSetUser::NIF,
        FormFieldsSetUser::NUMBER,
        FormFieldsSetUser::P_ID,
        FormFieldsSetUser::PHONE,
        FormFieldsSetUser::VAT
    ];

    private const CHECKOUT_SHIPPING_FIELDS = [
        FormFieldsSetUser::ADDRESS,
        FormFieldsSetUser::ADDRESS_ADDITIONAL_INFORMATION,
        FormFieldsSetUser::COMPANY,
        FormFieldsSetUser::FAX,
        FormFieldsSetUser::FIRST_NAME,
        FormFieldsSetUser::LAST_NAME,
        FormFieldsSetUser::MOBILE,
        FormFieldsSetUser::NIF,
        FormFieldsSetUser::NUMBER,
        FormFieldsSetUser::PHONE,
        FormFieldsSetUser::VAT
    ];

    private const ADDRESS_BOOK_FIELDS = [
        FormFieldsSetUser::ADDRESS,
        FormFieldsSetUser::ADDRESS_ADDITIONAL_INFORMATION,
        FormFieldsSetUser::ALIAS,
        FormFieldsSetUser::COMPANY,
        FormFieldsSetUser::FAX,
        FormFieldsSetUser::FIRST_NAME,
        FormFieldsSetUser::LAST_NAME,
        FormFieldsSetUser::MOBILE,
        FormFieldsSetUser::NIF,
        FormFieldsSetUser::NUMBER,
        FormFieldsSetUser::PHONE,
        FormFieldsSetUser::VAT
    ];

    private const REGISTER_USER_FIELDS = [
        FormFieldsSetUser::ADDRESS,
        FormFieldsSetUser::COMPANY,
        FormFieldsSetUser::EMAIL,
        FormFieldsSetUser::FIRST_NAME,
        FormFieldsSetUser::GENDER,
        FormFieldsSetUser::LAST_NAME,
        FormFieldsSetUser::NIF,
        FormFieldsSetUser::PASSWORD,
        FormFieldsSetUser::PASSWORD_RETYPE,
        FormFieldsSetUser::PHONE,
        FormFieldsSetUser::SUBSCRIBED,
        FormFieldsSetUser::VAT
    ];

    private const ACCOUNT_FIELDS = [
        FormAccountFields::DESCRIPTION,
        FormAccountFields::EMAIL,
        FormAccountFields::P_ID
    ];

    private const REGISTERED_USER_FIELDS = [
        FormRegisteredUserFields::BIRTHDAY,
        FormRegisteredUserFields::FIRST_NAME,
        FormRegisteredUserFields::GENDER,
        FormRegisteredUserFields::JOB,
        FormRegisteredUserFields::LAST_NAME,
        FormRegisteredUserFields::REGISTERED_USER_EMAIL,
        FormRegisteredUserFields::REGISTERED_USER_P_ID,
        FormRegisteredUserFields::REGISTERED_USER_USERNAME,
        FormRegisteredUserFields::SUBSCRIBED
    ];

    private const PROFILE_FIELDS = [
        FormRegisteredUserFields::BIRTHDAY,
        FormRegisteredUserFields::FIRST_NAME,
        FormRegisteredUserFields::GENDER,
        FormRegisteredUserFields::LAST_NAME,
        FormRegisteredUserFields::REGISTERED_USER_P_ID,
        FormRegisteredUserFields::REGISTERED_USER_USERNAME
    ];

    public function modify(array $configurationData, string $routeType): array {
        if (in_array($routeType, [InternalCheckout::ADD_CUSTOMER, InternalCheckout::OSC_RECALCULATE, InternalCheckout::NEXT_STEP], true)) {
            $pages = self::publishedWidgets(SpecialPagePId::CHECKOUT);
            $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::USER_FIELDS] = $this->withCheckoutFields(
                $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::USER_FIELDS],
                array_values(array_unique(self::fieldKeys($pages)))
            );
            $configurationData = self::withTabAccountType($configurationData, $pages);
        } elseif ($routeType === InternalUser::SET_ADDRESS_BOOK) {
            $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::ADDRESSBOOK_FIELDS] = $this->withAddressBookFields(
                $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::ADDRESSBOOK_FIELDS],
                self::addressKeys(self::publishedWidgets(SpecialPagePId::CHECKOUT), '', '')
            );
        } elseif ($routeType === InternalUser::ADD_USER) {
            $pages = self::publishedWidgets(SpecialPagePId::ACCOUNT);
            $composed = self::subpagesOf($pages, 'accountRegister');
            $userTypeKeys = self::userTypeKeys($composed, '');
            $userFields = $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::USER_FIELDS];
            $configurationData[Configuration::FORMS][Forms::SET_USER][FormSetUser::USER_FIELDS] = $userTypeKeys
                ? $this->withComposedRegisterFields($userFields, $userTypeKeys)
                : $this->withRegisterFields($userFields, self::settingsOf($pages, 'accountRegister'));
            $configurationData = $userTypeKeys
                ? self::withTabAccountType($configurationData, $composed)
                : self::withAccountType($configurationData, $pages, 'accountRegister');
        } elseif ($routeType === InternalAccount::UPDATE_ACCOUNT) {
            $account = $configurationData[Configuration::FORMS][Forms::ACCOUNT];
            foreach (self::nodesOf(self::publishedWidgets(SpecialPagePId::ACCOUNT), 'accountEdit') as $node) {
                $keys = self::composedKeys($node->getSubpages());
                $settings = $node->getModuleSettings();
                $accountKeys = $keys ? array_values(array_intersect(self::ACCOUNT_FIELDS, $keys)) : self::switchedOn($settings, 'af_', self::ACCOUNT_FIELDS);
                $userKeys = $keys ? array_values(array_intersect(self::REGISTERED_USER_FIELDS, $keys)) : self::switchedOn($settings, 'ruf_', self::REGISTERED_USER_FIELDS);
                $account[FormAccount::FIELDS] = self::included($account[FormAccount::FIELDS] ?? [], $accountKeys);
                $account[FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS] = self::included($account[FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS] ?? [], $userKeys);
            }
            $configurationData[Configuration::FORMS][Forms::ACCOUNT] = $account;
        } elseif ($routeType === InternalAccount::UPDATE_REGISTERED_USER && self::settingsOf(self::publishedWidgets(SpecialPagePId::ACCOUNT), 'registeredUserProfile')) {
            $fields = $configurationData[Configuration::FORMS][Forms::ACCOUNT][FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS] ?? [];
            $configurationData[Configuration::FORMS][Forms::ACCOUNT][FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS] = self::included($fields, self::PROFILE_FIELDS);
        } elseif (in_array($routeType, CheckoutRoute::oneStepRoutes(), true) && !($routeType === RouteType::CHECKOUT_BASKET && PluginProperties::loadSpecialPage(SpecialPagePId::BASKET) !== null) && self::publishedWidgets(SpecialPagePId::CHECKOUT)) {
            $configurationData[Configuration::COMMERCE][Commerce::USE_ONE_STEP_CHECKOUT] = true;
        }
        return CaptchaRule::apply($configurationData, $routeType);
    }

    private function withRegisterFields(array $userFields, array $widgetsSettings): array {
        foreach ($widgetsSettings as $settings) {
            foreach ($userFields[FormSetUserFields::FIELDS_BY_USER_TYPE] as $userType => $byUserType) {
                if (!self::isOn($settings, 'ut_' . $userType . '_included')) {
                    continue;
                }
                $fields = $byUserType[FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS] ?? [];
                $userFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS] = self::included($fields, self::switchedOn($settings, 'f_' . $userType . '_', self::REGISTER_USER_FIELDS));
            }
        }
        return $userFields;
    }

    private function withComposedRegisterFields(array $userFields, array $userTypeKeys): array {
        $allowed = array_unique(array_merge(self::REGISTER_USER_FIELDS, self::CHECKOUT_USER_FIELDS));
        foreach ($userTypeKeys as $userType => $keys) {
            if (!isset($userFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType])) {
                continue;
            }
            $fields = $userFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS] ?? [];
            $userFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS] = self::included($fields, array_values(array_intersect($allowed, $keys)));
        }
        return $userFields;
    }

    /**
     * Field keys of a composed form, as [userType][] (the user type tab node carries the userType).
     *
     * @param array $pages
     * @param string $userType
     *
     * @return array
     */
    private static function userTypeKeys(array $pages, string $userType): array {
        $keys = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $settings = $page->getModuleSettings();
            $pageUserType = is_string($settings['userType'] ?? null) && $settings['userType'] !== '' ? $settings['userType'] : $userType;
            if ($pageUserType === self::GENERAL_TAB) {
                $pageUserType = UserType::PARTICULAR;
            }
            $own = self::composedKeys([$page], false);
            if ($pageUserType !== '' && $own) {
                $keys[$pageUserType] = array_values(array_unique(array_merge($keys[$pageUserType] ?? [], $own)));
            }
            foreach (self::userTypeKeys($page->getSubpages(), $pageUserType) as $type => $typeKeys) {
                $keys[$type] = array_values(array_unique(array_merge($keys[$type] ?? [], $typeKeys)));
            }
        }
        return $keys;
    }

    /**
     * Field keys the field widgets of a composed form post.
     *
     * @param array $pages
     * @param bool $deep
     *
     * @return array
     */
    private static function composedKeys(array $pages, bool $deep = true): array {
        $keys = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $type = $page->getCustomType();
            if ($type === 'checkoutFormField') {
                $keys[] = self::fieldKeyOf($page->getModuleSettings());
            } elseif ($type === 'checkoutGenderField') {
                $keys[] = FormFieldsSetUser::GENDER;
            } elseif ($type === 'checkoutCustomTags') {
                $keys[] = FormFieldsSetUser::CUSTOM_TAGS;
            } elseif ($type === 'checkoutNewsletterCheck') {
                $keys[] = FormFieldsSetUser::SUBSCRIBED;
            } elseif ($type === 'checkoutAccountCreation') {
                array_push($keys, FormFieldsSetUser::PASSWORD, FormFieldsSetUser::PASSWORD_RETYPE);
            }
            if ($deep) {
                $keys = array_merge($keys, self::composedKeys($page->getSubpages()));
            }
        }
        return array_values(array_unique(array_filter($keys, 'is_string')));
    }

    /**
     * Subpages of every node of the given widget type in the tree.
     *
     * @param array $pages
     * @param string $type
     *
     * @return array
     */
    private static function subpagesOf(array $pages, string $type): array {
        return array_merge([], ...array_map(fn(Page $node): array => $node->getSubpages(), self::nodesOf($pages, $type)));
    }

    /**
     * Every node of the given widget type in the tree.
     *
     * @param array $pages
     * @param string $type
     *
     * @return array
     */
    private static function nodesOf(array $pages, string $type): array {
        $nodes = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === $type) {
                $nodes[] = $page;
            }
            $nodes = array_merge($nodes, self::nodesOf($page->getSubpages(), $type));
        }
        return $nodes;
    }

    private static function switchedOn(array $settings, string $prefix, array $keys): array {
        return array_values(array_filter($keys, fn(string $key): bool => self::isOn($settings, $prefix . $key . '_included')));
    }

    private static function isOn(array $settings, string $name): bool {
        return in_array($settings[$name] ?? null, [true, 'true', 1, '1'], true);
    }

    private static function withTabAccountType(array $configurationData, array $pages): array {
        if (self::nodesOf($pages, 'checkoutUserTypes') === []) {
            return $configurationData;
        }
        $configurationData[Configuration::ACCOUNT][Account::ACCOUNT_TYPE] = RequestQuery::postedFlag(self::GENERAL_ACCOUNT_PARAM) ? GeneralRestriction::ONLY_GENERAL : GeneralRestriction::NON_GENERAL;
        return $configurationData;
    }

    private static function withAccountType(array $configurationData, array $pages, string $type): array {
        $accountTypes = array_values(array_unique(self::settingValues($pages, $type, 'generalRestriction')));
        if (count($accountTypes) === 1 && GeneralRestriction::isValid($accountTypes[0])) {
            $configurationData[Configuration::ACCOUNT][Account::ACCOUNT_TYPE] = $accountTypes[0];
        }
        return $configurationData;
    }

    private function withCheckoutFields(array $userFields, array $fieldKeys): array {
        $userKeys = array_intersect(self::CHECKOUT_USER_FIELDS, $fieldKeys);
        foreach ($userFields[FormSetUserFields::FIELDS_BY_USER_TYPE] as $userType => $byUserType) {
            $fields = $byUserType[FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS];
            $userFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::USER][FormSetUserFieldsByUserTypeElement::FIELDS] = self::included($fields, $userKeys);
        }
        $userFields[FormSetUserFields::SHIPPING_FIELDS] = self::included($userFields[FormSetUserFields::SHIPPING_FIELDS], array_intersect(self::CHECKOUT_SHIPPING_FIELDS, $fieldKeys));
        return $userFields;
    }

    private function withAddressBookFields(array $addressBookFields, array $keys): array {
        foreach ($keys[SetUserTypeForms::BILLING] ?? [] as $userType => $fieldKeys) {
            if (!isset($addressBookFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::BILLING])) {
                continue;
            }
            $fields = $addressBookFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::BILLING][FormSetUserFieldsByUserTypeElement::FIELDS] ?? [];
            $addressBookFields[FormSetUserFields::FIELDS_BY_USER_TYPE][$userType][FormSetUserFieldsByUserType::BILLING][FormSetUserFieldsByUserTypeElement::FIELDS] = self::included($fields, array_intersect(self::ADDRESS_BOOK_FIELDS, $fieldKeys));
        }
        $shippingKeys = array_merge([], ...array_values($keys[SetUserTypeForms::SHIPPING] ?? []));
        if ($shippingKeys) {
            $addressBookFields[FormSetUserFields::SHIPPING_FIELDS] = self::included($addressBookFields[FormSetUserFields::SHIPPING_FIELDS] ?? [], array_intersect(self::ADDRESS_BOOK_FIELDS, $shippingKeys));
        }
        return $addressBookFields;
    }

    /** checkoutFormField keys inside each addressSection modal, as [section][userType][] (the tab node carries the userType). */
    private static function addressKeys(array $pages, string $section, string $userType): array {
        $keys = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $settings = $page->getModuleSettings();
            $pageSection = $page->getCustomType() === 'addressSection' ? (string)($settings['section'] ?? '') : $section;
            $pageUserType = $pageSection !== '' && is_string($settings['userType'] ?? null) ? $settings['userType'] : $userType;
            if ($pageSection !== '' && $pageUserType !== '' && $page->getCustomType() === 'checkoutFormField' && is_string(self::fieldKeyOf($settings))) {
                $keys[$pageSection][$pageUserType][] = self::fieldKeyOf($settings);
            }
            $keys = array_merge_recursive($keys, self::addressKeys($page->getSubpages(), $pageSection, $pageUserType));
        }
        return $keys;
    }

    /** The widget tree of a published singleton mff_* page, read from the same blob the storefront renders. */
    private static function publishedWidgets(string $pId): array {
        $params = new PageParametersGroup();
        $params->setPId($pId);
        $collection = Loader::service(Services::PAGE)->getPages($params);
        $page = $collection instanceof ElementCollection ? ($collection->getItems()[0] ?? null) : null;
        $document = $page instanceof SdkPage ? PageDocument::fromJson($page->getLanguage()?->getPageContent()) : null;
        return $document?->toPages()?->getItems() ?? [];
    }

    private static function settingValues(array $pages, string $type, string $setting): array {
        return array_filter(array_map(fn(array $settings) => $settings[$setting] ?? null, self::settingsOf($pages, $type)), 'is_string');
    }

    /** The moduleSettings of every node of the given widget type in the tree. */
    private static function settingsOf(array $pages, string $type): array {
        $settings = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === $type) {
                $settings[] = $page->getModuleSettings();
            }
            $settings = array_merge($settings, self::settingsOf($page->getSubpages(), $type));
        }
        return $settings;
    }

    private static function fieldKeys(array $pages): array {
        $keys = [];
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            if ($page->getCustomType() === 'addressSection') {
                continue;
            }
            if ($page->getCustomType() === 'checkoutFormField') {
                $keys[] = self::fieldKeyOf($page->getModuleSettings());
            } elseif ($page->getCustomType() === 'checkoutGenderField') {
                $keys[] = FormFieldsSetUser::GENDER;
            } elseif ($page->getCustomType() === 'checkoutCustomTags') {
                $keys[] = FormFieldsSetUser::CUSTOM_TAGS;
            }
            $keys = array_merge($keys, self::fieldKeys($page->getSubpages()));
        }
        return array_filter($keys, 'is_string');
    }

    /** checkoutFormField keeps one key selector per fieldContext (user / shipping / address / logged), each offering what that context can save. */
    private static function fieldKeyOf(array $settings): ?string {
        $key = match ($settings['fieldContext'] ?? 'user') {
            'address' => $settings['addressFieldKey'] ?? null,
            'shipping' => $settings['shippingFieldKey'] ?? null,
            'logged' => $settings['loggedFieldKey'] ?? null,
            'account' => $settings['accountFieldKey'] ?? null,
            default => $settings['fieldKey'] ?? null
        };
        return is_string($key) ? $key : null;
    }

    private static function included(array $fields, array $keys): array {
        foreach ($keys as $key) {
            if (!($fields[$key][FormField::INCLUDED] ?? false)) {
                $fields[$key] = [FormField::INCLUDED => true, FormField::REQUIRED => false];
            }
        }
        return $fields;
    }
}
