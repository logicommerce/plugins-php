<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Form;

use FWK\Core\Theme\Theme;
use FWK\Core\Theme\Dtos\Configuration;
use FWK\Core\Theme\Dtos\Forms;
use FWK\Core\Theme\Dtos\FormSetUser;
use FWK\Core\Theme\Dtos\FormSetUserFields;
use FWK\Core\Theme\Dtos\FormSetUserFieldsByUserType;
use FWK\Core\Theme\Dtos\FormSetUserFieldsByUserTypeElement;
use FWK\Core\Theme\Dtos\FormField;
use FWK\Core\Theme\Dtos\FormFieldsSetUser;
use FWK\Core\Theme\Dtos\FormAccount;
use FWK\Core\Theme\Dtos\FormMaster;
use FWK\Core\Theme\Dtos\FormRegisteredUser;
use SDK\Enums\UserType;

/**
 * Builds a merged FWK Configuration DTO identical to the store's current
 * configuration except that the account/register (setUser) field settings are
 * replaced by widget-driven values.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Form
 */
class ConfigurationOverrideBuilder {

    private const BRANCH_FIELDS = FormSetUser::USER_FIELDS;

    private const BRANCH_ELEMENT = FormSetUserFieldsByUserType::USER;

    private const USER_TYPES = [
        UserType::PARTICULAR,
        UserType::BUSINESS,
        UserType::FREELANCE,
    ];

    private const FIELD_KEY_MAP = [
        FormFieldsSetUser::FIRST_NAME => FormFieldsSetUser::FIRST_NAME,
        FormFieldsSetUser::LAST_NAME => FormFieldsSetUser::LAST_NAME,
        FormFieldsSetUser::COMPANY => FormFieldsSetUser::COMPANY,
        FormFieldsSetUser::VAT => FormFieldsSetUser::VAT,
        FormFieldsSetUser::NIF => FormFieldsSetUser::NIF,
        FormFieldsSetUser::EMAIL => FormFieldsSetUser::EMAIL,
        FormFieldsSetUser::PHONE => FormFieldsSetUser::PHONE,
        FormFieldsSetUser::ADDRESS => FormFieldsSetUser::ADDRESS,
        FormFieldsSetUser::LOCATION => FormFieldsSetUser::LOCATION,
        FormFieldsSetUser::SUBSCRIBED => FormFieldsSetUser::SUBSCRIBED,
        FormFieldsSetUser::PASSWORD => FormFieldsSetUser::PASSWORD,
        FormFieldsSetUser::PASSWORD_RETYPE => FormFieldsSetUser::PASSWORD_RETYPE,
        FormFieldsSetUser::USE_SHIPPING_ADDRESS => FormFieldsSetUser::USE_SHIPPING_ADDRESS,
        FormFieldsSetUser::CUSTOM_TAGS => FormFieldsSetUser::CUSTOM_TAGS,
        FormFieldsSetUser::MOBILE => FormFieldsSetUser::MOBILE,
    ];

    public static function build(array $fieldConfig): Configuration {
        $data = Theme::getInstance()->getConfigurationData();
        $userTypes = $fieldConfig['userTypes'] ?? [];
        $fields = $fieldConfig['fields'] ?? [];
        foreach (self::USER_TYPES as $userType) {
            if (isset($userTypes[$userType])) {
                self::applyUserTypeNode($data, $userType, $userTypes[$userType]);
            }
            if (isset($fields[$userType])) {
                self::applyFields($data, $userType, $fields[$userType]);
            }
        }
        return new Configuration($data);
    }

    private static function applyUserTypeNode(array &$data, string $userType, array $node): void {
        $typeRef = &self::userTypeRef($data, $userType);
        if (isset($node['included'])) {
            $typeRef[FormSetUserFieldsByUserType::INCLUDED] = (bool) $node['included'];
        }
        if (isset($node['priority'])) {
            $typeRef[FormSetUserFieldsByUserType::PRIORITY] = (int) $node['priority'];
        }
    }

    public static function buildEdit(array $editFieldConfig): Configuration {
        $data = Theme::getInstance()->getConfigurationData();
        $account = $editFieldConfig['account'] ?? [];
        $registeredUser = $editFieldConfig['registeredUser'] ?? [];
        if ($account !== []) {
            $accountRef = &self::accountFieldsRef($data);
            self::writeFieldEntries($accountRef, $account);
            unset($accountRef);
        }
        if ($registeredUser !== []) {
            $registeredRef = &self::registeredUserFieldsRef($data);
            self::writeFieldEntries($registeredRef, $registeredUser);
            unset($registeredRef);
        }
        return new Configuration($data);
    }

    private static function applyFields(array &$data, string $userType, array $entries): void {
        $typeRef = &self::userTypeRef($data, $userType);
        if (!isset($typeRef[self::BRANCH_ELEMENT][FormSetUserFieldsByUserTypeElement::FIELDS])) {
            $typeRef[self::BRANCH_ELEMENT][FormSetUserFieldsByUserTypeElement::FIELDS] = [];
        }
        $fieldsRef = &$typeRef[self::BRANCH_ELEMENT][FormSetUserFieldsByUserTypeElement::FIELDS];
        self::writeFieldEntries($fieldsRef, $entries);
    }

    private static function writeFieldEntries(array &$fieldsRef, array $entries): void {
        foreach ($entries as $entry) {
            if (!isset($entry['key'])) {
                continue;
            }
            $fieldKey = self::FIELD_KEY_MAP[$entry['key']] ?? $entry['key'];
            if (!isset($fieldsRef[$fieldKey])) {
                $fieldsRef[$fieldKey] = [];
            }
            if (isset($entry['included'])) {
                $fieldsRef[$fieldKey][FormField::INCLUDED] = (bool) $entry['included'];
            }
            if (isset($entry['priority'])) {
                $fieldsRef[$fieldKey][FormField::PRIORITY] = (int) $entry['priority'];
            }
            if (isset($entry['required'])) {
                $fieldsRef[$fieldKey][FormField::REQUIRED] = (bool) $entry['required'];
            }
        }
    }

    private static function &accountFieldsRef(array &$data): array {
        if (!isset($data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::FIELDS])) {
            $data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::FIELDS] = [];
        }
        $ref = &$data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::FIELDS];
        return $ref;
    }

    private static function &registeredUserFieldsRef(array &$data): array {
        if (!isset($data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS])) {
            $data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS] = [];
        }
        $ref = &$data[Configuration::FORMS][Forms::ACCOUNT][FormAccount::MASTER][FormMaster::REGISTERED_USER][FormRegisteredUser::FIELDS];
        return $ref;
    }

    private static function &userTypeRef(array &$data, string $userType): array {
        if (!isset($data[Configuration::FORMS][Forms::SET_USER][self::BRANCH_FIELDS][FormSetUserFields::FIELDS_BY_USER_TYPE][$userType])) {
            $data[Configuration::FORMS][Forms::SET_USER][self::BRANCH_FIELDS][FormSetUserFields::FIELDS_BY_USER_TYPE][$userType] = [];
        }
        $ref = &$data[Configuration::FORMS][Forms::SET_USER][self::BRANCH_FIELDS][FormSetUserFields::FIELDS_BY_USER_TYPE][$userType];
        return $ref;
    }
}
