<?php

namespace App\Exceptions;

use Exception;

class InsurerTemporaryFailureException extends Exception
{
    public function __construct(
        string $message = "Provider temporarily unavailable.",
        public readonly ?array $payload = null
    ) {
        parent::__construct($message, 503);
    }
}
