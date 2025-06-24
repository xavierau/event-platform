<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use Illuminate\Support\Facades\DB;

class RedeemUserCouponAction
{
    public function __construct(
        private FindUserCouponByCodeAction $findAction,
        private ValidateUserCouponForRedemptionAction $validateAction,
        private IncrementCouponUsageAction $incrementAction,
        private CreateCouponUsageLogAction $logAction
    ) {}

    public function execute(
        string $uniqueCode,
        ?string $location = null,
        ?array $details = null
    ): array {
        // Step 1: Find the user coupon by unique code
        $userCoupon = $this->findAction->execute($uniqueCode);

        if (!$userCoupon) {
            return [
                'success' => false,
                'message' => 'Coupon not found',
                'user_coupon' => null,
                'usage_log' => null,
            ];
        }

        // Step 2: Validate the coupon for redemption
        $validation = $this->validateAction->execute($userCoupon);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Coupon validation failed',
                'validation_errors' => $validation['reasons'],
                'validation_details' => $validation['details'],
                'user_coupon' => $userCoupon,
                'usage_log' => null,
            ];
        }

        // Step 3: Perform redemption in atomic transaction
        try {
            return DB::transaction(function () use ($userCoupon, $location, $details) {
                // Step 3a: Increment usage and update status
                $updatedUserCoupon = $this->incrementAction->execute($userCoupon);

                // Step 3b: Create usage log
                $usageLog = $this->logAction->execute($updatedUserCoupon, $location, $details);

                return [
                    'success' => true,
                    'message' => 'Coupon redeemed successfully',
                    'user_coupon' => $updatedUserCoupon,
                    'usage_log' => $usageLog,
                ];
            });
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Redemption failed due to system error',
                'error' => $e->getMessage(),
                'user_coupon' => $userCoupon,
                'usage_log' => null,
            ];
        }
    }
}
