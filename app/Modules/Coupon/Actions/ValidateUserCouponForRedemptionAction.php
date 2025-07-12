<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Carbon\Carbon;

class ValidateUserCouponForRedemptionAction
{
    public function execute(UserCoupon $userCoupon): array
    {
        $reasons = [];
        $valid = true;

        // Check if coupon has expired (date-based on UserCoupon or parent Coupon, or status-based)
        $hasExpired = ($userCoupon->expires_at && $userCoupon->expires_at->isPast())
            || ($userCoupon->coupon && $userCoupon->coupon->expires_at && $userCoupon->coupon->expires_at->isPast())
            || $userCoupon->status === UserCouponStatusEnum::EXPIRED;

        if ($hasExpired) {
            $reasons[] = 'Coupon has expired';
            $valid = false;
        }

        // Check if coupon status is fully used
        if ($userCoupon->status === UserCouponStatusEnum::FULLY_USED) {
            $reasons[] = 'Coupon has been fully used';
            $valid = false;
        }

        // Check usage limits (only if not already marked as fully used)
        if (
            $userCoupon->status !== UserCouponStatusEnum::FULLY_USED
            && $userCoupon->times_used >= $userCoupon->times_can_be_used
        ) {
            $reasons[] = 'Coupon usage limit reached';
            $valid = false;
        }

        return [
            'valid' => $valid,
            'reasons' => $reasons,
            'details' => [
                'remaining_uses' => max(0, $userCoupon->times_can_be_used - $userCoupon->times_used),
                'expires_at' => $userCoupon->expires_at,
                'status' => $userCoupon->status,
                'times_used' => $userCoupon->times_used,
                'times_can_be_used' => $userCoupon->times_can_be_used,
            ],
        ];
    }
}
