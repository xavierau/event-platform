<?php

namespace App\Modules\Coupon\Actions;

use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Models\UserCoupon;

class IssueCouponToUserAction
{
    public function __construct(
        private IssueSingleCouponAction $singleCouponAction,
        private IssueBulkCouponsAction $bulkCouponsAction
    ) {}

    /**
     * Issue coupon(s) to a user
     *
     * @param IssueCouponData $issuanceData
     * @param int $quantity Default is 1 for single issuance
     * @return UserCoupon[] Array of issued user coupons
     */
    public function execute(IssueCouponData $issuanceData, int $quantity = 1): array
    {
        if ($quantity === 1) {
            // Use single issuance action for efficiency
            $userCoupon = $this->singleCouponAction->execute($issuanceData);
            return [$userCoupon];
        } else {
            // Use bulk issuance action for multiple coupons
            return $this->bulkCouponsAction->execute($issuanceData, $quantity);
        }
    }
}
