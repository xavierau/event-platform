<?php

namespace App\Modules\Coupon\Actions;

use App\Models\User;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Carbon\Carbon;

class ValidateCouponEligibilityAction
{
    public function execute(Coupon $coupon, User $user): array
    {
        // Check if coupon is not yet valid
        if ($coupon->valid_from && Carbon::now()->isBefore($coupon->valid_from)) {
            return [
                'eligible' => false,
                'reason' => 'Coupon is not yet valid',
            ];
        }

        // Check if coupon has expired
        if ($coupon->expires_at && Carbon::now()->isAfter($coupon->expires_at)) {
            return [
                'eligible' => false,
                'reason' => 'Coupon has expired',
            ];
        }

        // Check maximum issuance limit
        if ($coupon->max_issuance !== null) {
            $issuedCount = UserCoupon::where('coupon_id', $coupon->id)->count();
            if ($issuedCount >= $coupon->max_issuance) {
                return [
                    'eligible' => false,
                    'reason' => 'Maximum issuance limit reached',
                ];
            }
        }

        // Check if user already has this single-use coupon
        if ($coupon->type === CouponTypeEnum::SINGLE_USE) {
            $userHasCoupon = UserCoupon::where('coupon_id', $coupon->id)
                ->where('user_id', $user->id)
                ->exists();

            if ($userHasCoupon) {
                return [
                    'eligible' => false,
                    'reason' => 'User already has this single-use coupon',
                ];
            }
        }

        // All checks passed
        return [
            'eligible' => true,
            'reason' => null,
        ];
    }
}
