<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductBaseDTO;
use FWK\Core\Resources\Session;
use FWK\Core\Theme\Theme;
use SDK\Application;
use SDK\Core\Dtos\PluginProperties;

/**
 * This is the class BaseMapper
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 */
class BaseMapper {

    protected ?PluginProperties $pluginProperties = null;

    protected function __construct(?PluginProperties $pluginProperties) {
        $this->pluginProperties = $pluginProperties;
    }

    /**
     * Map the product summary data
     * 
     * @param $product
     * 
     * @return ProductBaseDTO
     */
    protected function mapProductSummary($product): ProductBaseDTO {
        $productDTO = new ProductBaseDTO(
            $product->getId(),
            $product->getLanguage()->getName()
        );
        $prices = $this->getPrices($product);
        $price = $prices['price'];
        $basePrice = $prices['basePrice'];
        $productDTO->setReference($this->getReference($product));
        $productDTO->setProductUrl($this->formatProductUrl($product->getLanguage()->getUrlSeo()));
        $productDTO->setThumbnail($product->getMainImages()->getSmallImage());
        $productDTO->setFormattedPrice($this->formatCurrency($price));
        $productDTO->setFormattedPrevPrice($this->formatCurrency($basePrice));
        return $productDTO;
    }

    protected function getPrices($product): array {
        $price = 0;
        $basePrice = 0;
        $priceByQuantitySelected = $this->getPriceByQuantitySelected();
        if ($priceByQuantitySelected != '' && $priceByQuantitySelected != 'none') {
            $priceByQuantity = $this->getPriceByQuantity($product, $priceByQuantitySelected);
            $price = $priceByQuantity['price'];
            $basePrice = $priceByQuantity['basePrice'];
        }
        if ($price == 0 || $basePrice == 0) {
            $priceByOptionSelected = $this->getPriceByOptionSelected();
            if ($priceByOptionSelected != '' && $priceByOptionSelected != 'none') {
                $priceByQuantity = $this->getPriceByOption($product, $priceByOptionSelected);
                $price = $priceByQuantity['price'];
                $basePrice = $priceByQuantity['basePrice'];
            }
        }
        if ($price == 0 || $basePrice == 0) {
            if ($this->getShowTaxIncluded()) {
                $price = $product->getCombinationData()->getPricesWithTaxes()->getRetailPrice();
                $basePrice = $product->getCombinationData()->getPricesWithTaxes()->getBasePrice();
            } else {
                $price = $product->getCombinationData()->getPrices()->getRetailPrice();
                $basePrice = $product->getCombinationData()->getPrices()->getBasePrice();
            }
        }
        if (!$product->getDefinition()->getOffer()) {
            $price = $basePrice;
        }
        $prices = [
            'price' => $price,
            'basePrice' => $basePrice
        ];
        return $prices;
    }

    /**
     * Format the product url
     * 
     * @param $url
     * 
     * @return string
     */
    protected function formatProductUrl($url) {
        return $this->getBaseUrl() . $url;
    }

    /**
     * Format the date time
     * 
     * @param $dateTime
     * 
     * @return string
     */
    protected function formatDateTime($dateTime): string {
        $date = $dateTime->getDateTime();
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Convert the date time
     * 
     * @param $dateString
     * 
     * @return string
     */
    protected function convertDateTime($dateString): string {
        if ($dateString === null || $dateString === '') {
            return '';
        }
        $date = date("Y-m-d H:i:s", strtotime($dateString));
        return $date;
    }

    /**
     * Format the currency
     * 
     * @param $value
     * 
     * @return string
     */
    protected function formatCurrency($value) {
        if (!is_numeric($value)) {
            return '';
        }
        $amount = floatval($value);
        $formattedAmount = number_format($amount, 2, '.', '');
        $parts = explode('.', $formattedAmount);
        $parts[0] = number_format($parts[0]);
        $currencyCode = Session::getInstance()->getGeneralSettings()->getCurrency();
        $locale = Session::getInstance()->getGeneralSettings()->getLocale();
        $fmt = numfmt_create($locale, \NumberFormatter::CURRENCY);
        $currencySymbol = numfmt_get_symbol($fmt, \NumberFormatter::CURRENCY_SYMBOL);
        $output = implode('.', $parts) . ' ' . $currencySymbol;
        foreach (Application::getInstance()->getCurrenciesSettings() as $currency) {
            if ($currency->getCode() === $currencyCode) {
                $output = str_replace($currencySymbol, $currency->getSymbol(), $output);
                return $output;
            }
        }
        return $output;

    }

    /**
     * Get the price by quantity
     *
     * @param $product
     *
     * @return array
     */
    protected function getPriceByQuantity($product, $selected) {
        $price = null;
        $basePrice = null;
        $priceByQuantity = $this->getPricesRespectingTaxSetting($product)->getPricesByQuantity();
        $this->selectPriceByQuantity($priceByQuantity, $selected, $price, $basePrice);
        if ($price === null || $basePrice === null) {
            foreach ($product->getOptions() as $option) {
                foreach ($option->getValues() as $value) {
                    $priceByQuantity = $this->getPricesRespectingTaxSetting($value)->getPricesByQuantity();
                    $this->selectPriceByQuantity($priceByQuantity, $selected, $price, $basePrice);
                }
            }
        }
        return [
            'price' => $price,
            'basePrice' => $basePrice
        ];
    }

    protected function getPriceByOption($product, $selected) {
        $price = null;
        $basePrice = null;
        $this->selectPriceByOption($product->getOptions(), $selected, $price, $basePrice);
        return [
            'price' => $price,
            'basePrice' => $basePrice
        ];
    }

    private function getPricesRespectingTaxSetting($item) {
        if ($this->getShowTaxIncluded()) {
            return $item->getPricesWithTaxes();
        } else {
            return $item->getPrices();
        }
    }

    private function selectPriceByQuantity(array $pricesByQuantityList, string $selected, &$price, &$basePrice): void {
        foreach ($pricesByQuantityList as $priceByQuantity) {
            $prices = $priceByQuantity->getPrices();
            $this->setPrice($price, $basePrice, $prices, $selected);
        }
    }

    private function selectPriceByOption($options, $selected, &$price, &$basePrice): void {
        foreach ($options as $option) {
            foreach ($option->getValues() as $value) {
                if ($this->getShowTaxIncluded()) {
                    $prices = $value->getPricesWithTaxes();
                } else {
                    $prices = $value->getPrices();
                }
                $this->setPrice($price, $basePrice, $prices, $selected);
            }
        }
    }

    private function setPrice(&$price, &$basePrice, $prices, $selected) {
        $tmpPrice = $prices->getRetailPrice();
        $tmpBasePrice = $prices->getBasePrice();
        if (
            $price === null
            || ($selected === 'max' && $tmpPrice > $price)
            || ($selected === 'base' && $tmpPrice < $price)
        ) {
            $price = $tmpPrice;
            $basePrice = $tmpBasePrice;
        }
    }

    /**
     * Get the option price
     * 
     * @param $product
     * 
     * @return array
     */
    protected function getOptionPrice($product) {
        $price = 1000000000;
        $pricePrev = 1000000000;
        foreach ($product->getOptions() as $option) {
            foreach ($option->getValues() as $value) {
                if ($this->getShowTaxIncluded()) {
                    $tmpPrice = $value->getPricesWithTaxes()->getRetailPrice();
                    $tmpPricePrev = $value->getPricesWithTaxes()->getBasePrice();
                } else {
                    $tmpPrice = $value->getPrices()->getRetailPrice();
                    $tmpPricePrev = $value->getPrices()->getBasePrice();
                }
                if ($tmpPrice <= $price) {
                    $price = $tmpPrice;
                }
                if ($tmpPricePrev <= $pricePrev) {
                    $pricePrev = $tmpPricePrev;
                }
            }
        }
        if ($price === 1000000000) {
            $price = 0;
        }
        if ($pricePrev === 1000000000) {
            $pricePrev = 0;
        }
        return [ "price" => $price, "pricePrev" => $pricePrev ];
    }

    /**
     * Round the price
     * 
     * @param $price
     * 
     * @return float
     */
    protected function roundPrice($price): float {
        return round($price, 2);
    }

    private function getBaseUrl() {
        $url = Session::getInstance()->getGeneralSettings()->getStoreURL();
        $url = parse_url($url);
        $url = $url['scheme'] . '://' . $url['host'];
        return $url;
    }

    protected function getShowTaxIncluded() {
        $showTaxesIncluded = Theme::getInstance()->getConfiguration()?->getCommerce()?->showTaxesIncluded();
        if (!is_null($showTaxesIncluded)) {
            return $showTaxesIncluded;
        }
        return false;
    }

    protected function getReference($product) {
        $referenceTypeValue = $this->getReferenceTypeValue();
        $productCodes = $product->getCombinationData()->getProductCodes();
        $productCode = "";
        switch (strtolower($referenceTypeValue)) {
            case 'sku':
                $productCode = $productCodes->getSku();
                break;
            case 'ean':
                $productCode = $productCodes->getEan();
                break;
            case 'upc':
                $productCode = $productCodes->getUpc();
                break;
            case 'isbn':
                $productCode = $productCodes->getIsbn();
                break;
            case 'jan':
                $productCode = $productCodes->getJan();
                break;
            default:
                $productCode = $productCodes->getSku();
        }
        if (empty($productCode)) {
            $productCode = $this->getProductCode($product);
        }
        return $productCode;
    }

    private function getProductCode($product) {
        $referenceTypeValue = $this->getReferenceTypeValue();
        $productCodes = $product->getCodes();
        $productCode = "";
        switch (strtolower($referenceTypeValue)) {
            case 'sku':
                $productCode = $productCodes->getSku();
                break;
            case 'ean':
                $productCode = $productCodes->getEan();
                break;
            case 'upc':
                $productCode = $productCodes->getUpc();
                break;
            case 'isbn':
                $productCode = $productCodes->getIsbn();
                break;
            case 'jan':
                $productCode = $productCodes->getJan();
                break;
            default:
                $productCode = $productCodes->getSku();
        }
        if (empty($productCode)) {
            $productCode = $product->getId();
        }
        return $productCode;
    }

    private function getReferenceTypeValue(): string {
        $referenceType = constant('Plugins\\ComLogicommerceOct8ne\\Enums\\PluginPropertiesPropertyNames::REFERENCETYPE');
        return $this->getPluginPropertyValue($referenceType);
    }

    private function getPriceByQuantitySelected(): string {
        $priceByQuantitySelected = constant('Plugins\\ComLogicommerceOct8ne\\Enums\\PluginPropertiesPropertyNames::PRICEBYQUANTITY');
        return $this->getPluginPropertyValue($priceByQuantitySelected);
    }

    private function getPriceByOptionSelected(): string {
        $priceByOptionSelected = constant('Plugins\\ComLogicommerceOct8ne\\Enums\\PluginPropertiesPropertyNames::PRICEBYOPTION');
        return $this->getPluginPropertyValue($priceByOptionSelected);
    }

    /**
     * Get the plugin properties
     *
     * @return PluginProperties|null
     */
    protected function getPluginProperties(): ?PluginProperties {
        return $this->pluginProperties;
    }

    /**
     * Get the plugin property value
     *
     * @param string $name
     *
     * @return string
     */
    protected function getPluginPropertyValue($name): string {
        foreach ($this->pluginProperties->getProperties() as $property) {
            if ($name == $property->getName()) {
                return $property->getValue();
            }
        }
        return "";
    }

}
