<?php

use App\Modules\Coupon\Actions\ValidateUserCouponForRedemptionAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Models\User;
use Carbon\Carbon;

describe('ValidateUserCouponForRedemptionAction', function () {
    beforeEach(function () {
        $this->action = new ValidateUserCouponForRedemptionAction();
        $this->user = User::factory()->create();
        $this->coupon = Coupon::factory()->create([
            'type' => CouponTypeEnum::MULTI_USE,
        ]);
    });

    it('validates an active coupon successfully', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 1,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeTrue()
            ->and($result['reasons'])->toBeEmpty();
    });

    it('rejects an expired coupon', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::EXPIRED,
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toContain('Coupon has expired');
    });

    it('rejects a fully used coupon', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_can_be_used' => 2,
            'times_used' => 2,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toContain('Coupon has been fully used');
    });

    it('rejects coupon that reached usage limit', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 3,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toContain('Coupon usage limit reached');
    });

    it('rejects coupon that expires today but expired by time', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'expires_at' => Carbon::now()->subHour(),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toContain('Coupon has expired');
    });

    it('validates coupon that expires later today', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeTrue()
            ->and($result['reasons'])->toBeEmpty();
    });

    it('collects multiple validation failures', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_can_be_used' => 1,
            'times_used' => 1,
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toHaveCount(2)
            ->and($result['reasons'])->toContain('Coupon has expired')
            ->and($result['reasons'])->toContain('Coupon has been fully used');
    });

    it('validates single use coupon that hasnt been used', function () {
        $singleUseCoupon = Coupon::factory()->create([
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $singleUseCoupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeTrue()
            ->and($result['reasons'])->toBeEmpty();
    });

    it('provides detailed validation information', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 5,
            'times_used' => 2,
            'expires_at' => Carbon::now()->addDays(10),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['valid', 'reasons', 'details'])
            ->and($result['details']['remaining_uses'])->toBe(3)
            ->and($result['details']['expires_at'])->toBeInstanceOf(Carbon::class)
            ->and($result['details']['status'])->toBe(UserCouponStatusEnum::ACTIVE);
    });

    it('handles coupon with null expiry date', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1,
            'times_used' => 0,
            'expires_at' => null,
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeTrue()
            ->and($result['reasons'])->toBeEmpty()
            ->and($result['details']['expires_at'])->toBeNull();
    });

    it('handles zero usage coupon validation', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 0, // edge case: no uses allowed
            'times_used' => 0,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute($userCoupon);

        expect($result)->toBeArray()
            ->and($result['valid'])->toBeFalse()
            ->and($result['reasons'])->toContain('Coupon usage limit reached');
    });
});
