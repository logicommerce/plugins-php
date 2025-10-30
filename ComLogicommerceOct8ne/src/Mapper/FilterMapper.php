<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use FWK\Enums\Parameters;
use FWK\Enums\LanguageLabels;
use Plugins\ComLogicommerceOct8ne\Dtos\Filter\AppliedFilterDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Filter\AvailableFilterDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Filter\FilterOptionDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Filter\FilterInfoDTO;

/**
 * This is the class FilterMapper to map the filter data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see FilterInfoDTO
 */
class FilterMapper extends BaseMapper {

    private ?object $filters;

    private ?string $appliedFilter;

    private ?array $available = [];

    private ?array $applied = [];

    public function __construct($filters) {
        $this->filters = $filters;
    }

    public function setAppliedFilter($appliedFilter) {
        $this->appliedFilter = $appliedFilter;
    }

    /**
     * Map the filter data
     * 
     * @return object
     */
    public function map(): object {
        if (is_null($this->filters)) {
            $filterInfoDTO = new FilterInfoDTO();
            return $filterInfoDTO;
        }
        /*
        if (count($this->filters->getCategories())) {
            $available[] = $this->addAvailableFilterCategories(
                $this->filters->getCategories(), 
                Parameters::CATEGORY_ID_LIST, 
                LanguageLabels::CATEGORIES);
        }*/
        if (count($this->filters->getBrands())) {
            $this->addAvailableFilterCategories(
                $this->filters->getBrands(), 
                Parameters::BRANDS_LIST, 
                LanguageLabels::BRANDS);
        }
        $filtersInfoDTO = new FilterInfoDTO();
        $filtersInfoDTO->setAvailable($this->available);
        $filtersInfoDTO->setApplied($this->applied);
        return $filtersInfoDTO;
    }

    private function addAvailableFilterCategories($filters, $param, $filterName): void {
        $filterOptions = [];
        $filterDTO = new AvailableFilterDTO($param, $filterName);
        foreach ($filters as $filter) {
            $found = false;
            if ($this->appliedFilter != null || $this->appliedFilter != '') {
                $appliedFilters = explode(',', $this->appliedFilter);
                foreach ($appliedFilters as $appliedFilter) {
                    if ($appliedFilter == $filter->getId()) {
                        $this->applied[] = new AppliedFilterDTO($param, $filterName, $filter->getId(), $filter->getName());
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                foreach ($filterOptions as $filterOption) {
                    if ($filterOption->getValueLabel() == $filter->getName()) {
                        $value = $filterOption->getValue() . ',' . $filter->getId();
                        $filterOption->setValue($value);
                        $found = true;
                        break;
                    }
                }
            }
            if (!$found) {
                $filterOptionDTO = new FilterOptionDTO($filter->getId(), $filter->getName(), 1);
                $filterOptions[] = $filterOptionDTO;
            }
        }
        $filterDTO->setOptions($filterOptions);
        $this->available[] = $filterDTO;
    }
}