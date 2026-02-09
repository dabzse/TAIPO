<?php

namespace App\Exception;

use Exception;

class DatabaseConnectionException extends Exception
{
    public function __construct(string $message = "Database connection failed")
    {
        parent::__construct($message);
    }
}
