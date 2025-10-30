<?php

namespace Plugins\ComLogicommerceOct8ne\Dtos\Common;

/**
 * This is the error class.
 * The API error data will be stored in that class and will remain immutable (only get methods are available)
 * 
 * @package Plugins\ComLogicommerceOct8ne\Dtos\Common
 * 
 * @see \JsonSerializable
 */
class ErrorDTO implements \JsonSerializable {

    private string $message;

    private bool $error = true;

    public function __construct() {
    }

    function setMessage(string $message) {
        $this->message = $message;
    }

    function getMessage() {
        return $this->message;
    }

    function setError(bool $error) {
        $this->error = $error;
    }

    function getError() {
        return $this->error;
    }

    public function jsonSerialize(): array {
        return [
            'message' => $this->message,
            'error' => $this->error
        ];
    }
}