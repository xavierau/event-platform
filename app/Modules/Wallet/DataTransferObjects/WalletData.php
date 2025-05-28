<?php

namespace App\Modules\Wallet\DataTransferObjects;

use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class WalletData extends Data
{
    public function __construct(
        #[Required]
        public readonly int $user_id,

        #[Min(0)]
        public readonly int $points_balance = 0,

        #[Min(0)]
        public readonly int $kill_points_balance = 0,

        #[Min(0)]
        public readonly int $total_points_earned = 0,

        #[Min(0)]
        public readonly int $total_points_spent = 0,

        #[Min(0)]
        public readonly int $total_kill_points_earned = 0,

        #[Min(0)]
        public readonly int $total_kill_points_spent = 0,
    ) {}

    public static function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points_balance' => ['integer', 'min:0'],
            'kill_points_balance' => ['integer', 'min:0'],
            'total_points_earned' => ['integer', 'min:0'],
            'total_points_spent' => ['integer', 'min:0'],
            'total_kill_points_earned' => ['integer', 'min:0'],
            'total_kill_points_spent' => ['integer', 'min:0'],
        ];
    }
}
