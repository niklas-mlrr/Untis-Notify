<?php
namespace Exceptions;

class AuthenticationException extends \Exception {
    public function __construct($message = "Authentication error", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
