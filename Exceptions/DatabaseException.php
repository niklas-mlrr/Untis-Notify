<?php
namespace Exceptions;

class DatabaseException extends \Exception {
    public function __construct($message = "Database error", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
