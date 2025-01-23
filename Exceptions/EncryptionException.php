<?php
namespace Exceptions;

class EncryptionException extends \Exception {
    public function __construct($message = "Encryption error", $code = 0, \Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}
