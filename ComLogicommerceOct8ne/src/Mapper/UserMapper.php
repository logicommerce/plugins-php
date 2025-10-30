<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\User\UserDTO;
use Plugins\ComLogicommerceOct8ne\Dtos\Product\ProductInCartDTO;
use SDK\Core\Dtos\PluginProperties;

/**
 * This is the class UserMapper to map the user data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see UserDTO
 * @see ProductInCartDTO
 */
class UserMapper extends BaseMapper {

    private ?Object $user;

    private ?Object $basket;

    private ?array $shoppingListProducts;

    public function __construct($user, $basket, $shoppingListProducts, ?PluginProperties $pluginProperties = null) {
        $this->user = $user;
        $this->basket = $basket;
        $this->shoppingListProducts = $shoppingListProducts;
        parent::__construct($pluginProperties);
    }

    /**
     * Map the user data
     * 
     * @return object
     */
    public function map(): mixed {
        $user = $this->user;
        $selectedBillingAddressId = $user->getSelectedBillingAddressId();
        $billingAdress = $this->getBillingAddress($user, $selectedBillingAddressId);
        $id = $user->getId();
        $userDTO = new UserDTO($id);
        $userDTO->setEmail($user->getEmail());
        if ($billingAdress != null) {
            $userDTO->setFirstName($billingAdress->getFirstName());
            $userDTO->setLastName($billingAdress->getLastName());
        }
        $cart = $this->getBasketItems($this->basket);
        $userDTO->setCart($cart);
        $userDTO->setWishList($this->getWishList());
        return $userDTO;
    }

    private function getWishList() {
        $products = $this->shoppingListProducts;
        $productDTOs = [];
        foreach ($products as $product) {
            $productDTO = $this->mapProductSummary($product);
            $productDTOs[] = $productDTO;
        }
        return $productDTOs;
    }

    private function getBasketItems($basket): array {
        $basketItems = [];
        foreach($basket->getItems() as $basketItem){
            $item = new ProductInCartDTO($basketItem->getQuantity());
            $item->setInternalId($basketItem->getId());
            $item->setTitle($basketItem->getName());
            $item->setFormattedPrice($this->formatCurrency($basketItem->getTotal()));
            $item->setFormattedPrevPrice($this->formatCurrency($basketItem->getSubtotal()));
            $item->setThumbnail($this->getThumbnail($basketItem));
            $item->setProductUrl($this->formatProductUrl($basketItem->getUrlSeo()));
            $basketItems[] = $item;
        }
        return $basketItems;
    }

    private function getBillingAddress($user, $selectedBillingAddressId) {
        $selectedBillingAddress = null;
        foreach($user->getBillingAddresses() as $billingAddres) {
            if ($billingAddres->getId() == $selectedBillingAddressId) {
                $selectedBillingAddress = $billingAddres;
		        break;
            }
        }  
        return $selectedBillingAddress;
    }

    private function getThumbnail($basketItem) {
        $thumbnail = null;
        if ($basketItem->getImages()->getSmallImage() != null) {
            $thumbnail = $basketItem->getImages()->getSmallImage();
        }
        return $thumbnail;
    }
}