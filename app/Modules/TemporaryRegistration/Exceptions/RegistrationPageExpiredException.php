<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Exceptions;

use Exception;

class RegistrationPageExpiredException extends Exception
{
    public function __construct(string $message = 'This registration page has expired.')
    {
        parent::__construct($message);
    }
}
