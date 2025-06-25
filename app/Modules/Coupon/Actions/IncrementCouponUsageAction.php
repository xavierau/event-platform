<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;

class IncrementCouponUsageAction
{
    public function execute(UserCoupon $userCoupon): UserCoupon
    {
        // Only increment if coupon is active and has remaining uses
        if (
            $userCoupon->status === UserCouponStatusEnum::ACTIVE
            && $userCoupon->times_used < $userCoupon->times_can_be_used
        ) {

            $userCoupon->times_used++;

            // Check if coupon should be marked as fully used
            if ($userCoupon->times_used >= $userCoupon->times_can_be_used) {
                $userCoupon->status = UserCouponStatusEnum::FULLY_USED;
            }

            $userCoupon->save();
        }

        return $userCoupon;
    }
}
