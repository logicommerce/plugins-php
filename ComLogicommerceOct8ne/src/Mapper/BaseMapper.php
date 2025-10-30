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

    protected function __construct($pluginProperties) {
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
        if ($this->getShowTaxIncluded()) {
            $price = $product->getCombinationData()->getPricesWithTaxes()->getRetailPrice();
            $basePrice = $product->getCombinationData()->getPricesWithTaxes()->getBasePrice();
        } else {
            $price = $product->getCombinationData()->getPrices()->getRetailPrice();
            $basePrice = $product->getCombinationData()->getPrices()->getBasePrice();
        }
        if ($price == 0 || $basePrice == 0) {
            $priceByQuantity =  $this->getPriceByQuantity($product);
            $price = $priceByQuantity['price'];
            $basePrice = $priceByQuantity['basePrice'];
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
    protected function getPriceByQuantity($product) {
        foreach ($product->getOptions() as $option) {
            foreach ($option->getValues() as $value) {
                foreach ($value->getPrices()->getPricesByQuantity() as $priceByQuantity) {
                    $price = $priceByQuantity->getPrices()->getRetailPrice();
                    $basePrice = $priceByQuantity->getPrices()->getBasePrice();
                    break 2;
                }
            }
        }
        return [
            'price' => $price,
            'basePrice' => $basePrice
        ];
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