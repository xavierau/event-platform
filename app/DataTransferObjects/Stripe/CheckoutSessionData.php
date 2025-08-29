<?php

namespace App\DataTransferObjects\Stripe;

use Spatie\LaravelData\Data;

class CheckoutSessionData extends Data
{
    public function __construct(
        public readonly string $price_id,
        public readonly string $success_url,
        public readonly string $cancel_url,
        public readonly ?string $customer_email = null,
        public readonly ?array $metadata = [],
        public readonly ?int $trial_days = null,
        public readonly bool $allow_promotion_codes = true,
    ) {}
}