<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Exceptions;

use Exception;

class RegistrationPageInactiveException extends Exception
{
    public function __construct(string $message = 'This registration page is not active.')
    {
        parent::__construct($message);
    }
}
