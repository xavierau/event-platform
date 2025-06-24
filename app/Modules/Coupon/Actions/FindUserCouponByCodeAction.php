<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;

class FindUserCouponByCodeAction
{
    public function execute(string $uniqueCode): ?UserCoupon
    {
        // Handle empty or whitespace-only codes
        $uniqueCode = trim($uniqueCode);
        if (empty($uniqueCode)) {
            return null;
        }

        return UserCoupon::with(['user', 'coupon'])
            ->where('unique_code', $uniqueCode)
            ->first();
    }
}
