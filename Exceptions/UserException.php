<?php
namespace Exceptions;

class UserException extends \Exception {
    public function __construct($message = "User error", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
