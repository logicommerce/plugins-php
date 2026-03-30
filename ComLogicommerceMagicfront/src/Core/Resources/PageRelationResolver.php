<?php

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

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

                // Final type conversion
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

    // Removed: buildCustomPagesCss() - No longer used, CSS is now handled by CustomizeCssHandler
}
