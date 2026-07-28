<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Resources\Assets;
use FWK\Core\Resources\RoutePaths;
use FWK\Core\Resources\Session;
use FWK\Core\Resources\Utils;
use FWK\Enums\LanguageLabels;
use FWK\Enums\RouteTypes\InternalProduct;
use FWK\Enums\RouteTypes\InternalUser;
use SDK\Dtos\Common\Route;

/**
 * Labels and locale-bound constants the productBundles widget needs to render the product-option
 * selectors faithfully (boolean Sí/No, attachment upload labels, date-picker pattern, missing-option
 * image). The widget renderer has no access to the FWK languageSheet, framework constants or Assets
 * paths, so this resolver bakes them from the storefront controller (full FWK context) and attaches
 * the map to every widget page as `page.bundleLabels`. Empty off a product route / in the editor.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class BundleOptionsResolver {

    public static function build(array $languageSheet, Route $route, int $weekStart = 0): array {
        $shoppingLists = Session::getInstance()->getAggregateData()->getShoppingLists();
        $defaultList = $shoppingLists ? $shoppingLists->getDefaultOne() : null;
        return [
            'yes'                => $languageSheet[LanguageLabels::YES] ?? '',
            'no'                 => $languageSheet[LanguageLabels::NO] ?? '',
            'uploadFile'         => $languageSheet[LanguageLabels::UPLOAD_FILE] ?? '',
            'uploadFiles'        => $languageSheet[LanguageLabels::UPLOAD_FILES] ?? '',
            'attachMaxSize'      => defined('ATTACHMENT_MAX_SIZE') ? ATTACHMENT_MAX_SIZE : 20,
            'optionImageMissing' => Assets::getInstance()->getAssetsImagesPath(Assets::ENVIRONMENT_COMMERCE)
                . '/' . (defined('IMAGE_MISSING_OPTION_IMAGE') ? IMAGE_MISSING_OPTION_IMAGE : ''),
            'jsDatePattern'      => Utils::getJSDatePatternByLocale(Session::getInstance()->getGeneralSettings()->getLocale()),
            'language'           => $route->getLanguage(),
            'dateYears'          => '1910-' . date('Y'),
            'weekStart'          => $weekStart,
            'isLogged'           => Utils::isSessionLoggedIn(),
            'recommendLabel'     => $languageSheet[LanguageLabels::RECOMMEND_ITEM] ?? '',
            'slAdd'              => $languageSheet[LanguageLabels::ADD_TO_SHOPPING_LIST] ?? '',
            'slDelete'           => $languageSheet[LanguageLabels::DELETE_FROM_SHOPPING_LIST] ?? '',
            'slIds'              => $defaultList ? $defaultList->getProductIdList() : [],
            'recommendAction'    => RoutePaths::getPath(InternalProduct::SET_RECOMMEND),
            'slAddAction'        => RoutePaths::getPath(InternalUser::SET_SHOPPING_LIST_ROW),
            'slDelAction'        => RoutePaths::getPath(InternalUser::DELETE_SHOPPING_LIST_ROWS),
        ];
    }
}
