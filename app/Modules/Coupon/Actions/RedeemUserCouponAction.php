<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use Illuminate\Support\Facades\DB;
use Throwable;

class RedeemUserCouponAction
{
    public function __construct(
        private IncrementCouponUsageAction $incrementAction,
        private CreateCouponUsageLogAction $logAction
    ) {}

    public function execute(
        UserCoupon $userCoupon,
        ?string $location = null,
        ?array $details = null
    ): UserCoupon {
        try {
            return DB::transaction(function () use ($userCoupon, $location, $details) {
                // Step 1: Increment usage and update status
                $updatedUserCoupon = $this->incrementAction->execute($userCoupon);

                // Step 2: Create usage log
                $this->logAction->execute($updatedUserCoupon, $location, $details);

                return $updatedUserCoupon;
            });
        } catch (Throwable $e) {
            // Re-throw a more generic exception to avoid leaking implementation details
            throw new \RuntimeException('Coupon redemption failed due to a system error.', 0, $e);
        }
    }
}
