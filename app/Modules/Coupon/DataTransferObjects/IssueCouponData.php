<?php

namespace App\Modules\Coupon\DataTransferObjects;

use Spatie\LaravelData\Data;

class IssueCouponData extends Data
{
    public function __construct(
<<<<<<< HEAD
        public readonly int $user_id,
        public readonly int $coupon_id,
        public readonly ?int $times_can_be_used = 1,
=======
        public readonly int $coupon_id,
        public readonly int $user_id,
        public readonly int $times_can_be_used = 1,
        public readonly int $quantity = 1,
>>>>>>> feature/coupon-module
    ) {}
}
