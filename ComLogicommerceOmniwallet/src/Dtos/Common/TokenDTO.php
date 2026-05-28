<?php

namespace Plugins\ComLogicommerceOmniwallet\Dtos\Common;

/**
 * This is the token DTO class.
 * The API token data will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @package Plugins\ComLogicommerceOmniwallet\Dtos\Common
 * 
 * @see \JsonSerializable
 */
class TokenDTO implements \JsonSerializable {

    private string $token;
    private string $email;
    private string $type;
    private string $expiresAt;

    public function __construct() {
    }

    function setToken(string $token) {
        $this->token = $token;
    }

    function getToken() {
        return $this->token;
    }

    function setEmail(string $email) {
        $this->email = $email;
    }

    function getEmail() {
        return $this->email;
    }

    function setType(string $type) {
        $this->type = $type;
    }

    function getType() {
        return $this->type;
    }

    function setExpiresAt(string $expiresAt) {
        $this->expiresAt = $expiresAt;
    }

    function getExpiresAt() {
        return $this->expiresAt;
    }

    /**
     * Serialize the adapter info DTO
     * 
     * @return array
     */
    public function jsonSerialize(): array {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'type' => $this->type,
            'expiresAt' => $this->expiresAt
        ];
    }

}
