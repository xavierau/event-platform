<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\Exceptions;

use Exception;

class RegistrationPageFullException extends Exception
{
    public function __construct(string $message = 'This registration page has reached its maximum number of registrations.')
    {
        parent::__construct($message);
    }
}
