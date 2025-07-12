<?php

namespace App\Modules\Membership\DataTransferObjects;

use App\Modules\Membership\Enums\PaymentMethod;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class MembershipPurchaseData extends Data
{
    public function __construct(
        #[Required]
        public readonly int $user_id,

        #[Required]
        public readonly int $membership_level_id,

        #[Required]
        public readonly PaymentMethod $payment_method,

        public ?string $transaction_reference = null,
        public readonly bool $auto_renew = false,
        public readonly ?array $metadata = null,
    ) {}

    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'membership_level_id' => ['required', 'integer', 'exists:membership_levels,id'],
            'payment_method' => ['required', 'string'],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'auto_renew' => ['boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
