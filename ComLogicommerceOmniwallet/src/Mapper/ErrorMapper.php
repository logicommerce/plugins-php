<?php

namespace Plugins\ComLogicommerceOmniwallet\Mapper;

use Plugins\ComLogicommerceOmniwallet\Dtos\Common\ErrorDTO;

/**
 * This is the class ErrorMapper to map the error data
 * 
 * @package Plugins\ComLogicommerceOmniwallet\Mapper
 * 
 * @see ErrorDTO
 */
class ErrorMapper {

    private ?string $message;

    public function __construct(string $message) {
        $this->message = $message;
    }

    /**
     * Map the error data
     * 
     * @return object
     */
    public function map(): object {
        $errorDTO = new ErrorDTO();
        $errorDTO->setMessage($this->message);
        return $errorDTO;
    }

}
