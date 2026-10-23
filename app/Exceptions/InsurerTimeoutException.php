<?php

namespace App\Exceptions;

use Exception;

class InsurerTimeoutException extends Exception
{
    public function __construct(
        string $message = "Provider call timed out.",
        public readonly ?string $providerReference = null,
        public readonly ?array $payload = null
    ) {
        parent::__construct($message, 504);
    }
}
