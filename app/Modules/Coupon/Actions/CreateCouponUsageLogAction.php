<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use Carbon\Carbon;

class CreateCouponUsageLogAction
{
    public function execute(
        UserCoupon $userCoupon,
        ?string $location = null,
        ?array $details = null
    ): CouponUsageLog {
        return CouponUsageLog::create([
            'user_coupon_id' => $userCoupon->id,
            'user_id' => $userCoupon->user_id,
            'used_at' => Carbon::now(),
            'location' => $location,
            'details' => $details,
        ]);
    }
}
