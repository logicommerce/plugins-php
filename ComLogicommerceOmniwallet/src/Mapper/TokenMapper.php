<?php

namespace Plugins\ComLogicommerceOmniwallet\Mapper;

use Plugins\ComLogicommerceOmniwallet\Dtos\Common\TokenDTO;

/**
 * This is the class TokenMapper to map the token information
 * 
 * @package Plugins\ComLogicommerceOmniwallet\Mapper
 * 
 * @see TokenDTO
 */
class TokenMapper {

    private ?array $tokenData;

    public function __construct($tokenData) {
        $this->tokenData = $tokenData;
    }

    /**
     * Map the adapter info
     * 
     * @return object
     */
    public function map(): object {
        $tokenDTO = new TokenDTO();
        $tokenDTO->setToken($this->tokenData['token']);
        $tokenDTO->setEmail($this->tokenData['email']);
        $tokenDTO->setType($this->tokenData['type']);
        $tokenDTO->setExpiresAt($this->tokenData['expiresAt']);
        return $tokenDTO;
    }
}