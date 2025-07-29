<?php

namespace App\Modules\Coupon\DataTransferObjects;

use Spatie\LaravelData\Data;

class IssueCouponData extends Data
{
    public function __construct(
        public readonly int $coupon_id,
        public readonly int $user_id,
        public readonly int $times_can_be_used = 1,
        public readonly int $quantity = 1,
        public readonly ?int $issued_by_user_id = null,
        public readonly ?\DateTime $expires_at = null,
        public readonly ?string $assignment_method = null,
        public readonly ?string $assignment_reason = null,
        public readonly ?string $assignment_notes = null,
    ) {}
}
