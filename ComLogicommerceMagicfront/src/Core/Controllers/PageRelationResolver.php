<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Controllers;

use FWK\Core\Dtos\ElementCollection as DtosElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;

class PageRelationResolver {

    public const LC_PREFIX = 'lc-';

    public const PAGE_ID = 'page-id-';

    public const PAGES = 'pages';

    public static function setData(?ElementCollection $data): ?ElementCollection {
        if ($data === null || !$data instanceof ElementCollection)
            return null;

        $secondBatchRequests = new BatchRequests();
        $pages = $data;
        self::prepareSecondBatchRequestsRecursive($pages, $secondBatchRequests);
        $batchResults = self::sendSecondBatchRequestsRecursive($secondBatchRequests);
        self::addExtraDataBatchRecursive($batchResults, $pages);

        return $pages;
    }

    protected static function prepareSecondBatchRequestsRecursive(ElementCollection &$pages, BatchRequests $secondBatchRequests): void {
        $pages = DtosElementCollection::fillFromParentCollection($pages, Page::class);
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            self::addProductsGridBatchRequests($page, $secondBatchRequests);

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $page->getSubPages()]);
                self::prepareSecondBatchRequestsRecursive($subPages, $secondBatchRequests);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function sendSecondBatchRequestsRecursive(BatchRequests $secondBatchRequests): array {
        $batchService = BatchService::getInstance();
        return $batchService->send($secondBatchRequests);
    }

    protected static function addExtraDataBatchRecursive(array $batchResults, ?ElementCollection $pages = null): void {
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }

            $base = self::getBatchKey($page);

            $keyProducts   = $base . '_products';
            $keyCategories = $base . '_categories';

            if (isset($batchResults[$keyProducts])) {
                $page->setProducts($batchResults[$keyProducts]);
            }

            if (isset($batchResults[$keyCategories])) {
                $page->setCategories($batchResults[$keyCategories]);
            }

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $subItems]);
                self::addExtraDataBatchRecursive($batchResults, $subPages);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function addProductsGridBatchRequests(Page $page, BatchRequests $secondBatchRequests): void {
        $settings = $page->getModuleSettings();
        $baseKey = self::getBatchKey($page);
        $productService = Loader::service(Services::PRODUCT);
        // Productos
        if ($page->getCustomType() == 'productsGrid') {
            self::addBatchRequest(
                new ProductsParametersGroup(),
                $settings,
                self::LC_PREFIX,
                [$productService, 'addGetProducts'],
                $baseKey . '_products',
                $secondBatchRequests
            );
        }
        // Categorías
        /*$this->addBatchRequest(
            new CategoryParametersGroup(),
            $settings,
            self::LC_PREFIX,
            [$this->categoryService, 'addGetCategories'],
            $baseKey . '_categories'
        );*/
    }

    protected static function addBatchRequest(object $group, array $settings, string $prefix, callable $serviceAddMethod, string $batchKey, BatchRequests $secondBatchRequests): void {
        $group = self::buildParametersGroup($group, $settings, $prefix);
        $defaultgroup = new ProductsParametersGroup();
        if (count($group->toArray()) == count($defaultgroup->toArray())) {
            return;
        }
        $serviceAddMethod($secondBatchRequests, $batchKey, $group);
    }

    protected static function buildParametersGroup(object $group, array $settings, string $prefix): object {
        $ref = new \ReflectionClass($group);

        foreach ($ref->getProperties() as $prop) {
            $name = $prop->getName();
            $key = $prefix . $name;

            if (!array_key_exists($key, $settings)) {
                continue;
            }

            $value = $settings[$key];
            $setter = 'set' . ucfirst($name);

            if (!method_exists($group, $setter)) {
                continue;
            }

            $type = $prop->getType();
            if ($type) {
                $typeName = $type->getName();

                if ($typeName === 'int' && !is_numeric($value)) {
                    continue;
                }

                if ($typeName === 'float' && !is_numeric($value)) {
                    continue;
                }

                if ($typeName === 'bool') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($value === null) {
                        continue;
                    }
                }

                if ($typeName === 'string' && !is_string($value)) {
                    continue;
                }

                // conversión final
                settype($value, $typeName);
            }

            if ($name === 'categoryId' && $value == 0) {
                continue;
            }

            $group->$setter($value);
        }

        return $group;
    }

    protected static function getBatchKey(Page $page): string {
        return self::PAGE_ID . ($page->getId() == 0 ? $page->getDraftId() : (string)$page->getId());
    }

    protected static function getPageIdByBatchKey(string $key): string {
        if (strpos($key, self::PAGE_ID) !== 0) {
            return '';
        }
        return substr($key, strlen(self::PAGE_ID));
    }

    public static function buildCustomPagesCss(array $pages): string {
        $css = '';
        foreach ($pages as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $pageType   = $page->getPageType();
            $customType = $page->getCustomType();
            $id         = $page->getId();
            $subpages   = $page->getSubpages();
            $tagValues  = $page->getCustomTagValues();

            if ($pageType !== 'CUSTOM' || !$customType) {
                if (!empty($subpages)) {
                    $css .= self::buildCustomPagesCss($subpages);
                }
                continue;
            }

            $padding    = [];
            $margin     = [];
            $alignment  = null;
            $textColor  = null;
            $fontSize   = null;
            $cssId      = null;

            foreach ($tagValues as $tag) {
                $pid = $tag->getCustomTagPId();
                $val = $tag->getValue();

                // padding
                if (str_starts_with($pid, 'lc-padding-')) {
                    $padding[str_replace('lc-padding-', '', $pid)] = $val;
                }

                // margin
                if (str_starts_with($pid, 'lc-margin-')) {
                    $margin[str_replace('lc-margin-', '', $pid)] = $val;
                }

                // text color
                if ($pid === 'lc-textColor') {
                    $textColor = $val;          // ej: #ff0000
                }

                // font size
                if ($pid === 'lc-typographySize') {
                    $fontSize = $val;           // ej: 18px, 1.2rem
                }

                // alignment por valor
                if ($pid === 'lc-alignment') {
                    switch ($val) {
                        case 'lc-alignment_left':
                        case 'left':
                            $alignment = 'left';
                            break;
                        case 'lc-alignment_center':
                        case 'center':
                            $alignment = 'center';
                            break;
                        case 'lc-alignment_right':
                        case 'right':
                            $alignment = 'right';
                            break;
                        case 'lc-alignment_justify':
                        case 'justify':
                            $alignment = 'justify';
                            break;
                    }
                }

                // alignment por id de opción
                if (str_starts_with($pid, 'lc-alignment_')) {
                    $side = str_replace('lc-alignment_', '', $pid);
                    if (in_array($side, ['left', 'center', 'right', 'justify'], true)) {
                        $alignment = $side;
                    }
                }

                // css id
                if ($pid === 'lc-cssId') {
                    $trimmed = trim((string) $val);
                    if ($trimmed !== '') {
                        $cssId = $trimmed;
                    }
                }
            }

            // selector: si hay lc-cssId, usarlo; si no, fallback al original
            $selector = $cssId !== null
                ? '#' . $cssId
                : '#' . $customType . '-' . $id;

            $rule = "$selector { ";

            foreach ($padding as $side => $val) {
                $rule .= "padding-$side: {$val}; ";
            }

            foreach ($margin as $side => $val) {
                $rule .= "margin-$side: {$val}; ";
            }

            if ($alignment !== null) {
                $rule .= "text-align: {$alignment}; ";
            }

            if ($textColor !== null) {
                $rule .= "color: {$textColor}; ";
            }

            if ($fontSize !== null) {
                $rule .= "font-size: {$fontSize}; ";
            }

            $rule .= "}\n";

            if ($rule !== "$selector { }\n") {
                $css .= $rule;
            }

            if (!empty($subpages)) {
                $css .= self::buildCustomPagesCss($subpages);
            }
        }

        return $css;
    }
}
