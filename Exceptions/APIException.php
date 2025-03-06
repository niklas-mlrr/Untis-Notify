<?php
namespace Exceptions;

class APIException extends \Exception {
    public function __construct($message = "API error", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
