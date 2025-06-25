<?php

namespace App\Modules\Coupon\Services;

use App\Modules\Coupon\Actions\UpsertCouponAction;
use App\Modules\Coupon\Actions\IssueCouponToUserAction;
use App\Modules\Coupon\Actions\RedeemUserCouponAction;
use App\Modules\Coupon\Actions\ValidateUserCouponForRedemptionAction;
use App\Modules\Coupon\Actions\FindUserCouponByCodeAction;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Illuminate\Support\Collection;

class CouponService
{
    public function __construct(
        private UpsertCouponAction $upsertCouponAction,
        private IssueCouponToUserAction $issueCouponAction,
        private RedeemUserCouponAction $redeemCouponAction,
        private ValidateUserCouponForRedemptionAction $validateCouponAction,
        private FindUserCouponByCodeAction $findCouponByCodeAction
    ) {}

    /**
     * Create or update a coupon template
     *
     * @param CouponData $couponData
     * @return Coupon
     */
    public function upsertCoupon(CouponData $couponData): Coupon
    {
        return $this->upsertCouponAction->execute($couponData);
    }

    /**
     * Issue coupon to a user
     *
     * @param IssueCouponData $issuanceData
     * @param int $quantity Number of coupons to issue (default: 1)
     * @return UserCoupon[] Array of issued user coupons
     */
    public function issueCoupon(IssueCouponData $issuanceData, int $quantity = 1): array
    {
        return $this->issueCouponAction->execute($issuanceData, $quantity);
    }

    /**
     * Redeem a coupon by its unique code
     *
     * @param string $uniqueCode The unique code of the user coupon
     * @param string|null $location Optional location where redemption occurs
     * @param array|null $details Optional additional details for the redemption
     * @return array Redemption result with success status, message, and data
     */
    public function redeemCoupon(
        string $uniqueCode,
        ?string $location = null,
        ?array $details = null
    ): array {
        return $this->redeemCouponAction->execute($uniqueCode, $location, $details);
    }

    /**
     * Validate a coupon for redemption without actually redeeming it
     *
     * @param string $uniqueCode The unique code of the user coupon
     * @return array Validation result with validity status and details
     */
    public function validateCoupon(string $uniqueCode): array
    {
        $userCoupon = $this->findCouponByCodeAction->execute($uniqueCode);

        if (!$userCoupon) {
            return [
                'valid' => false,
                'reasons' => ['Coupon not found'],
                'user_coupon' => null,
                'details' => null,
            ];
        }

        $validation = $this->validateCouponAction->execute($userCoupon);

        return [
            'valid' => $validation['valid'],
            'reasons' => $validation['reasons'],
            'user_coupon' => $userCoupon,
            'details' => $validation['details'],
        ];
    }

    /**
     * Find a user coupon by its unique code
     *
     * @param string $uniqueCode
     * @return UserCoupon|null
     */
    public function findCouponByCode(string $uniqueCode): ?UserCoupon
    {
        return $this->findCouponByCodeAction->execute($uniqueCode);
    }

    /**
     * Get all coupons for a specific organizer
     *
     * @param int $organizerId
     * @return Collection<Coupon>
     */
    public function getCouponsForOrganizer(int $organizerId): Collection
    {
        return Coupon::where('organizer_id', $organizerId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all user coupons for a specific user
     *
     * @param int $userId
     * @param bool $activeOnly Whether to return only active coupons (default: false)
     * @return Collection<UserCoupon>
     */
    public function getUserCoupons(int $userId, bool $activeOnly = false): Collection
    {
        $query = UserCoupon::with(['coupon'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        if ($activeOnly) {
            $query->where('status', \App\Modules\Coupon\Enums\UserCouponStatusEnum::ACTIVE)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        return $query->get();
    }

    /**
     * Get a specific coupon by ID
     *
     * @param int $couponId
     * @return Coupon|null
     */
    public function getCouponById(int $couponId): ?Coupon
    {
        return Coupon::find($couponId);
    }

    /**
     * Delete a coupon template
     *
     * @param int $couponId
     * @return bool
     */
    public function deleteCoupon(int $couponId): bool
    {
        $coupon = Coupon::find($couponId);

        if (!$coupon) {
            return false;
        }

        return $coupon->delete();
    }

    /**
     * Get coupon usage statistics
     *
     * @param int $couponId
     * @return array
     */
    public function getCouponStatistics(int $couponId): array
    {
        $coupon = Coupon::with(['userCoupons.usageLogs'])->find($couponId);

        if (!$coupon) {
            return [
                'coupon_found' => false,
                'total_issued' => 0,
                'total_redeemed' => 0,
                'total_redemptions' => 0,
                'active_coupons' => 0,
                'expired_coupons' => 0,
                'fully_used_coupons' => 0,
            ];
        }

        $userCoupons = $coupon->userCoupons;
        $totalRedemptions = $userCoupons->sum(function ($userCoupon) {
            return $userCoupon->usageLogs->count();
        });

        $statusCounts = $userCoupons->groupBy('status')->map->count();

        return [
            'coupon_found' => true,
            'total_issued' => $userCoupons->count(),
            'total_redeemed' => $userCoupons->where('times_used', '>', 0)->count(),
            'total_redemptions' => $totalRedemptions,
            'active_coupons' => $statusCounts[\App\Modules\Coupon\Enums\UserCouponStatusEnum::ACTIVE->value] ?? 0,
            'expired_coupons' => $statusCounts[\App\Modules\Coupon\Enums\UserCouponStatusEnum::EXPIRED->value] ?? 0,
            'fully_used_coupons' => $statusCounts[\App\Modules\Coupon\Enums\UserCouponStatusEnum::FULLY_USED->value] ?? 0,
        ];
    }

    /**
     * Check if a coupon code is available (not taken by another coupon)
     *
     * @param string $code
     * @param int|null $excludeId Exclude this coupon ID from the check (useful for updates)
     * @return bool
     */
    public function isCouponCodeAvailable(string $code, ?int $excludeId = null): bool
    {
        $query = Coupon::where('code', $code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return !$query->exists();
    }
}
