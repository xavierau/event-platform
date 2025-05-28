<?php

namespace App\Modules\Wallet\DataTransferObjects;

use App\Modules\Wallet\Enums\WalletTransactionType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class WalletTransactionData extends Data
{
    public function __construct(
        #[Required]
        public readonly int $user_id,

        #[Required]
        public readonly int $wallet_id,

        #[Required]
        public readonly WalletTransactionType $transaction_type,

        #[Required, Min(1)]
        public readonly int $amount,

        #[Required]
        public readonly string $description,

        public readonly ?string $reference_type = null,
        public readonly ?int $reference_id = null,
        public readonly ?array $metadata = null,
    ) {}

    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'wallet_id' => ['required', 'integer', 'exists:user_wallets,id'],
            'transaction_type' => ['required', 'string'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
