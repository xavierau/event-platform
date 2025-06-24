<?php

namespace App\Modules\Coupon\Actions;

use App\Models\User;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Carbon\Carbon;
use InvalidArgumentException;

class IssueSingleCouponAction
{
    public function __construct(
        private ValidateCouponEligibilityAction $eligibilityValidator,
        private GenerateUniqueCodeAction $codeGenerator
    ) {}

    public function execute(IssueCouponData $issuanceData): UserCoupon
    {
        $coupon = Coupon::findOrFail($issuanceData->coupon_id);
        $user = User::findOrFail($issuanceData->user_id);

        // Validate eligibility
        $eligibilityResult = $this->eligibilityValidator->execute($coupon, $user);

        if (!$eligibilityResult['eligible']) {
            throw new InvalidArgumentException($eligibilityResult['reason']);
        }

        // Generate unique code
        $uniqueCode = $this->codeGenerator->execute();

        // Create UserCoupon
        $userCoupon = UserCoupon::create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => $uniqueCode,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => $issuanceData->times_can_be_used,
            'times_used' => 0,
            'expires_at' => $coupon->expires_at,
            'issued_at' => Carbon::now(),
        ]);

        return $userCoupon;
    }
}
