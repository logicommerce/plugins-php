<?php
namespace Plugins\ComLogicommerceOct8ne\Dtos\User;

/**
 * This is the user DTO class.
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\User
 * 
 * @see \JsonSerializable
 */
class UserDTO implements \JsonSerializable {

    private ?string $id = null;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private ?string $email = null;
    private array $wishlist = [];
    private array $cart = [];

    public function __construct($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }

    public function getFirstName() {
        return $this->firstName;
    }

    public function getLastName() {
        return $this->lastName;
    }

    public function getEmail() {
        return $this->email;
    }

    public function getWishlist() {
        return $this->wishlist;
    }

    public function getCart() {
        return $this->cart;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setFirstName($firstName) {
        $this->firstName = $firstName;
    }

    public function setLastName($lastName) {
        $this->lastName = $lastName;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function setWishlist($wishlist) {
        $this->wishlist = $wishlist;
    }

    public function setCart($cart) {
        $this->cart = $cart;
    }

    /**
     * Serialize the user DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'wishlist' => $this->wishlist,
            'cart' => $this->cart
        ];
    }
}