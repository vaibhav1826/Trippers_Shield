<?php

namespace App\Exceptions;

use Exception;

class InvalidStateTransitionException extends Exception
{
    public function __construct(string $from, string $to, string $message = '')
    {
        $defaultMsg = "Illegal state transition attempted from [{$from}] to [{$to}].";
        parent::__construct($message ?: $defaultMsg, 409);
    }
}
