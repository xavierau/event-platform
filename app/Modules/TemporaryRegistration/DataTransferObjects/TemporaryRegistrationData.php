<?php

declare(strict_types=1);

namespace App\Modules\TemporaryRegistration\DataTransferObjects;

use Illuminate\Validation\Rules\Password;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

class TemporaryRegistrationData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $mobile_number,
        public readonly string $password,
        #[Nullable]
        public readonly ?string $ip_address = null,
        #[Nullable]
        public readonly ?string $user_agent = null,
    ) {}

    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', Password::defaults()],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string', 'max:500'],
        ];
    }
}
