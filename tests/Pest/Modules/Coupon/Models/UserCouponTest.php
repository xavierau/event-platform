<?php

namespace Tests\Pest\Modules\Coupon\Models;

use App\Models\User;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use App\Modules\Coupon\Models\UserCoupon;

it('can create a user coupon', function () {
    $userCoupon = UserCoupon::factory()->create();

    expect($userCoupon)->toBeInstanceOf(UserCoupon::class)
        ->and($userCoupon->user)->toBeInstanceOf(User::class)
        ->and($userCoupon->coupon)->toBeInstanceOf(Coupon::class);
});

it('casts the status attribute to UserCouponStatusEnum', function () {
    $userCoupon = UserCoupon::factory()->create(['status' => UserCouponStatusEnum::ACTIVE]);

    expect($userCoupon->status)->toBeInstanceOf(UserCouponStatusEnum::class)
        ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE);
});

it('has many usage logs', function () {
    $userCoupon = UserCoupon::factory()->create();
    CouponUsageLog::factory()->count(2)->create(['user_coupon_id' => $userCoupon->id]);

    expect($userCoupon->usageLogs)->toHaveCount(2)
        ->each->toBeInstanceOf(CouponUsageLog::class);
});
