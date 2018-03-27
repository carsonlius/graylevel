<?php

namespace gray\level;

class ServerException extends \Exception {
    public function __construct($message = "") {
        parent::__construct($message);
    }
}
