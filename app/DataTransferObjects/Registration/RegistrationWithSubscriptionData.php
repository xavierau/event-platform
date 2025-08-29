<?php

namespace App\DataTransferObjects\Registration;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class RegistrationWithSubscriptionData extends Data
{
    public function __construct(
        #[Required, StringType]
        public readonly string $name,

        #[Required, Email]
        public readonly string $email,

        #[Required, StringType]
        public readonly string $mobile_number,

        #[Required, StringType]
        public readonly string $password,

        #[Required, StringType]
        public readonly string $password_confirmation,

        #[Required, StringType]
        public readonly string $selected_price_id,

        public readonly ?string $payment_method_id = null,
        public readonly ?array $metadata = []
    ) {}
}