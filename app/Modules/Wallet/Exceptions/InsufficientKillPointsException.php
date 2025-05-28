<?php

namespace App\Modules\Wallet\Exceptions;

use Exception;

class InsufficientKillPointsException extends Exception
{
    public function __construct(string $message = 'Insufficient kill points for this transaction', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
