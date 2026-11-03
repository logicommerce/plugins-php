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

    private ?array $appliedFilters = [];

    private ?array $available = [];

    private ?array $applied = [];

    public function __construct($filters) {
        $this->filters = $filters;
    }

    public function setAppliedFilter($appliedFilters) {
        $this->appliedFilters = $appliedFilters;
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
        if (count($this->filters->getCategories())) {
            $this->addAvailableFilter(
                $this->filters->getCategories(), 
                Parameters::CATEGORY_ID_LIST, 
                LanguageLabels::CATEGORIES);
        }
        if (count($this->filters->getBrands())) {
            $this->addAvailableFilter(
                $this->filters->getBrands(), 
                Parameters::BRANDS_LIST, 
                LanguageLabels::BRANDS);
        }
        if (count($this->filters->getCustomTags())) {
            $this->addAvailableFilterCustomTags($this->filters->getCustomTags());
        }
        $filtersInfoDTO = new FilterInfoDTO();
        $filtersInfoDTO->setAvailable($this->available);
        $filtersInfoDTO->setApplied($this->applied);
        return $filtersInfoDTO;
    }

    private function addAvailableFilterCustomTags(array $filterCustomTags) {
        foreach($filterCustomTags as $filterCustomTag) {
            $filters = $filterCustomTag->getCustomTagValues();
            $paramName = "filterCustomTag_" . $filterCustomTag->getId();
            $filterName = $filterCustomTag->getName();
            $this->addAvailableFilterCustomTagValues($filters, $paramName, $filterName);     
        }
    }

    private function addAvailableFilter(array $filters, string $param, string $filterName): void {
        $filterOptions = [];
        $filterDTO = new AvailableFilterDTO($param, $filterName);
        foreach ($filters as $filter) {
            if ($this->addAppliedFilter($param, $filterName, $filter)) {
                $this->applied[] = new AppliedFilterDTO($param, $filterName, $filter->getId(), $filter->getName());
            } else if (!$this->isFilterApplied($filter, $filterOptions)) {
                $filterOptionDTO = new FilterOptionDTO($filter->getId(), $filter->getName(), 1);
                $filterDTO->addOption($filterOptionDTO);
                $filterOptions[] = $filterOptionDTO;
            }
        }
        $this->available[] = $filterDTO;
    }

    private function addAppliedFilter(string $param, string $filterName, $filter) {
        foreach ($this->appliedFilters as $appliedFilter) {
            if (is_string($appliedFilter)) {
                $splitAppliedFilters = explode(',', $appliedFilter);
                foreach ($splitAppliedFilters as $splitAppliedFilter) {
                    if ($splitAppliedFilter == $filter->getId()) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function isFilterApplied($filter, array $filterOptions) {
        foreach ($filterOptions as $filterOption) {
            if ($filterOption->getValueLabel() == $filter->getName()) {
                $value = $filterOption->getValue() . ',' . $filter->getId();
                $filterOption->setValue($value);
                return true;
            }
        }
        return false;
    }

    private function addAvailableFilterCustomTagValues(array $filters, string $param, string $filterName): void {
        $filterOptions = [];
        $filterDTO = new AvailableFilterDTO($param, $filterName);
        foreach ($filters as $filter) {
            $found = false;
            foreach ($this->appliedFilters as $appliedFilter) {
                $found = $this->addAppliedFilterTags($appliedFilter, $param, $filterName, $filter);
            }
            if (!$found && !$this->isFilterCustomTagApplied($filter, $filterOptions)) {
                $filterOptionDTO = new FilterOptionDTO($filter->getValue(), $filter->getFilterValue(), 1);
                $filterDTO->addOption($filterOptionDTO);
                $filterOptions[] = $filterOptionDTO;
            }
        }
        $this->available[] = $filterDTO;
    }

    private function addAppliedFilterTags($appliedFilter, string $param, string $filterName, $filter) {
        foreach ($appliedFilter as $appliedFilteTag) {
            if (is_array($appliedFilteTag)) {
                if ($appliedFilteTag[0] == $filter->getValue()) {
                    $this->applied[] = new AppliedFilterDTO($param, $filterName, $filter->getValue(), $filter->getFilterValue());
                    return true;
                }
            }
        }
        return false;
    }

    private function isFilterCustomTagApplied($filter, array $filterOptions) {
        foreach ($filterOptions as $filterOption) {
            if ($filterOption->getValueLabel() == $filter->getFilterValue()) {
                $value = $filterOption->getValue() . ',' . $filter->getValue();
                $filterOption->setValue($value);
                return true;
            }
        }
        return false;
    }
}