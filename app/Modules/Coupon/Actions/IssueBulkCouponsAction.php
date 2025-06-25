<?php

namespace App\Modules\Coupon\Actions;

use App\Models\User;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Carbon\Carbon;
use InvalidArgumentException;

class IssueBulkCouponsAction
{
    public function __construct(
        private ValidateCouponEligibilityAction $eligibilityValidator,
        private GenerateUniqueCodeAction $codeGenerator
    ) {}

    public function execute(IssueCouponData $issuanceData, int $quantity): array
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }

        $coupon = Coupon::findOrFail($issuanceData->coupon_id);
        $user = User::findOrFail($issuanceData->user_id);

<<<<<<< HEAD
        // Validate eligibility first (includes checking if user already has single-use coupon)
        $eligibilityResult = $this->eligibilityValidator->execute($coupon, $user);

        if (!$eligibilityResult['eligible']) {
            throw new InvalidArgumentException($eligibilityResult['reason']);
        }

        // For single-use coupons, special validation
=======
        // For single-use coupons, validate quantity first
>>>>>>> feature/coupon-module
        if ($coupon->type === CouponTypeEnum::SINGLE_USE) {
            if ($quantity > 1) {
                throw new InvalidArgumentException('Cannot issue multiple single-use coupons to same user');
            }
        }

<<<<<<< HEAD
=======
        // Validate eligibility (includes checking if user already has single-use coupon)
        $eligibilityResult = $this->eligibilityValidator->execute($coupon, $user);

        if (!$eligibilityResult['eligible']) {
            throw new InvalidArgumentException($eligibilityResult['reason']);
        }

>>>>>>> feature/coupon-module
        // Check if bulk issuance would exceed max_issuance limit
        if ($coupon->max_issuance !== null) {
            $currentCount = UserCoupon::where('coupon_id', $coupon->id)->count();
            if ($currentCount + $quantity > $coupon->max_issuance) {
                throw new InvalidArgumentException('Bulk issuance would exceed maximum limit');
            }
        }

        $userCoupons = [];
        $issuedAt = Carbon::now();

        // Generate all coupons efficiently
        for ($i = 0; $i < $quantity; $i++) {
            $uniqueCode = $this->codeGenerator->execute();

            $userCoupon = UserCoupon::create([
                'user_id' => $user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => $uniqueCode,
                'status' => UserCouponStatusEnum::ACTIVE,
                'times_can_be_used' => $issuanceData->times_can_be_used,
                'times_used' => 0,
                'expires_at' => $coupon->expires_at,
                'issued_at' => $issuedAt,
            ]);

            $userCoupons[] = $userCoupon;
        }

        return $userCoupons;
    }
}
