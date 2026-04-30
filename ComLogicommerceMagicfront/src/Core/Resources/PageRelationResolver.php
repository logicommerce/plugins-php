<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\Resources;

use FWK\Core\Dtos\ElementCollection as DtosElementCollection;
use FWK\Core\Resources\Loader;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page;
use SDK\Core\Dtos\ElementCollection;
use SDK\Core\Resources\BatchRequests;
use SDK\Core\Services\BatchService;
use SDK\Core\Services\Parameters\Groups\ParametersGroup;
use SDK\Services\Parameters\Groups\Product\ProductsParametersGroup;

/**
 * Enriches the widget tree with related data (products, categories) by
 * collecting batch requests per-page and applying the results back onto
 * the tree.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\Resources
 */
class PageRelationResolver {

    public const LC_PREFIX = 'lc-';

    public const PAGE_ID = 'page-id-';

    public const PAGES = 'pages';

    public static function setData(?ElementCollection $data): ?ElementCollection {
        if ($data === null) {
            return null;
        }
        $pages = $data;
        $batchRequests = new BatchRequests();
        self::prepareBatchRequestsRecursive($pages, $batchRequests);
        $batchResults = BatchService::getInstance()->send($batchRequests);
        self::applyBatchResultsRecursive($batchResults, $pages);
        return $pages;
    }

    protected static function prepareBatchRequestsRecursive(ElementCollection &$pages, BatchRequests $batchRequests): void {
        $pages = DtosElementCollection::fillFromParentCollection($pages, Page::class);
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            self::addProductsGridBatchRequests($page, $batchRequests);

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $subItems]);
                self::prepareBatchRequestsRecursive($subPages, $batchRequests);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function applyBatchResultsRecursive(array $batchResults, ?ElementCollection $pages): void {
        if ($pages === null) {
            return;
        }
        foreach ($pages->getItems() as $page) {
            if (!$page instanceof Page) {
                continue;
            }
            $base = self::getBatchKey($page);
            if (isset($batchResults[$base . '_products'])) {
                $page->setProducts($batchResults[$base . '_products']);
            }
            if (isset($batchResults[$base . '_categories'])) {
                $page->setCategories($batchResults[$base . '_categories']);
            }

            $subItems = $page->getSubpages();
            if (!empty($subItems)) {
                $subPages = new ElementCollection(['items' => $subItems]);
                self::applyBatchResultsRecursive($batchResults, $subPages);
                $page->setFWKSubpages($subPages->getItems());
            }
        }
    }

    protected static function addProductsGridBatchRequests(Page $page, BatchRequests $batchRequests): void {
        if ($page->getCustomType() !== 'productsGrid') {
            return;
        }
        $productService = Loader::service(Services::PRODUCT);
        self::addBatchRequest(
            new ProductsParametersGroup(),
            $page->getModuleSettings(),
            self::LC_PREFIX,
            [$productService, 'addGetProducts'],
            self::getBatchKey($page) . '_products',
            $batchRequests
        );
    }

    protected static function addBatchRequest(
        ParametersGroup $group,
        array $settings,
        string $prefix,
        callable $serviceAddMethod,
        string $batchKey,
        BatchRequests $batchRequests
    ): void {
        $group = self::buildParametersGroup($group, $settings, $prefix);
        // Skip if the settings did not populate any filter on top of the defaults.
        if (count($group->toArray()) === count((new (get_class($group))())->toArray())) {
            return;
        }
        $serviceAddMethod($batchRequests, $batchKey, $group);
    }

    /**
     * Populate a parameters group from a `$prefix{propertyName}` keyed
     * settings array, coercing scalar values to each property's declared type.
     */
    protected static function buildParametersGroup(ParametersGroup $group, array $settings, string $prefix): ParametersGroup {
        foreach ((new \ReflectionClass($group))->getProperties() as $prop) {
            $name   = $prop->getName();
            $setter = 'set' . ucfirst($name);
            $key    = $prefix . $name;

            if (!array_key_exists($key, $settings) || !method_exists($group, $setter)) {
                continue;
            }

            $value = self::coerceToPropertyType($settings[$key], $prop->getType());
            if ($value === null && $prop->getType() !== null) {
                continue;
            }

            // Category 0 is the "no category" sentinel used by the editor.
            if ($name === 'categoryId' && (int) $value === 0) {
                continue;
            }

            $group->$setter($value);
        }
        return $group;
    }

    /**
     * Coerce `$value` to match the declared property type. Returns null when
     * the value can't safely be converted (the caller should then skip it).
     */
    private static function coerceToPropertyType(mixed $value, ?\ReflectionType $type): mixed {
        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }
        $typeName = $type->getName();
        return match ($typeName) {
            'int', 'float' => is_numeric($value) ? self::castScalar($value, $typeName) : null,
            'bool'         => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'string'       => is_string($value) ? $value : null,
            default        => $value,
        };
    }

    private static function castScalar(mixed $value, string $typeName): mixed {
        settype($value, $typeName);
        return $value;
    }

    protected static function getBatchKey(Page $page): string {
        return self::PAGE_ID . ($page->getId() === 0 ? $page->getDraftId() : (string) $page->getId());
    }
}
