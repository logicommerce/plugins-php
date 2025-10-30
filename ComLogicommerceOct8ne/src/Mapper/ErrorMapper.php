<?php

namespace Plugins\ComLogicommerceOct8ne\Mapper;

use Plugins\ComLogicommerceOct8ne\Dtos\Common\ErrorDTO;

/**
 * This is the class ErrorMapper to map the error data
 * 
 * @package Plugins\ComLogicommerceOct8ne\Mapper
 * 
 * @see BaseMapper
 * @see ErrorDTO
 */
class ErrorMapper extends BaseMapper {

    private ?string $message;

    public function __construct($message) {
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