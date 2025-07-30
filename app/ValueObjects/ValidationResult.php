<?php

namespace App\ValueObjects;

use App\Models\User;

class ValidationResult
{
    public function __construct(
        private readonly bool $isValid,
        private readonly ?string $error = null,
        private readonly ?User $user = null,
        private readonly ?array $data = null,
    ) {}

    public static function success(User $user, array $data): self
    {
        return new self(
            isValid: true,
            user: $user,
            data: $data
        );
    }

    public static function failure(string $error): self
    {
        return new self(
            isValid: false,
            error: $error
        );
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getData(): ?array
    {
        return $this->data;
    }
}