<?php

use App\Modules\Coupon\Actions\IncrementCouponUsageAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Models\User;

describe('IncrementCouponUsageAction', function () {
    beforeEach(function () {
        $this->action = new IncrementCouponUsageAction();
        $this->user = User::factory()->create();
    });

    it('increments usage count for multi-use coupon', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 1,
        ]);

        $originalTimesUsed = $userCoupon->times_used;
        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe($originalTimesUsed + 1)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE);
    });

    it('marks single-use coupon as fully used after first use', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::SINGLE_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1,
            'times_used' => 0,
        ]);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(1)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('marks multi-use coupon as fully used when reaching limit', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 2, // One use remaining
        ]);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(3)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('does not increment beyond usage limit', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_can_be_used' => 2,
            'times_used' => 2,
        ]);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(2) // Should not increment further
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('handles coupon with zero usage limit edge case', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 0, // Edge case: no uses allowed
            'times_used' => 0,
        ]);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(0) // Should not increment
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE); // Status unchanged
    });

    it('returns updated user coupon instance', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 5,
            'times_used' => 2,
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeInstanceOf(UserCoupon::class)
            ->and($result->id)->toBe($userCoupon->id)
            ->and($result->times_used)->toBe(3)
            ->and($result->status)->toBe(UserCouponStatusEnum::ACTIVE);
    });

    it('does not modify expired coupon', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::EXPIRED,
            'times_can_be_used' => 3,
            'times_used' => 1,
        ]);

        $originalTimesUsed = $userCoupon->times_used;
        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe($originalTimesUsed)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::EXPIRED);
    });

    it('handles large usage counts correctly', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1000,
            'times_used' => 500,
        ]);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(501)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE);
    });

    it('updates updated_at timestamp', function () {
        $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 1,
        ]);

        $originalUpdatedAt = $userCoupon->updated_at;

        // Ensure we have a different timestamp
        sleep(1);

        $this->action->execute($userCoupon);

        $userCoupon->refresh();
        expect($userCoupon->updated_at)->not->toBe($originalUpdatedAt);
    });
});
