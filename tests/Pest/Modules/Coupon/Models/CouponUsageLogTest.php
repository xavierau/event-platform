<?php

namespace Tests\Pest\Modules\Coupon\Models;

use App\Models\User;
use App\Modules\Coupon\Models\CouponUsageLog;
use App\Modules\Coupon\Models\UserCoupon;

it('can create a coupon usage log', function () {
    $log = CouponUsageLog::factory()->create();

    expect($log)->toBeInstanceOf(CouponUsageLog::class)
        ->and($log->userCoupon)->toBeInstanceOf(UserCoupon::class)
        ->and($log->redeemedBy)->toBeInstanceOf(User::class);
});

it('casts the context attribute to an array', function () {
    $log = CouponUsageLog::factory()->create([
        'context' => ['device_id' => 'abc-123']
    ]);

    expect($log->context)->toBeArray()
        ->and($log->context)->toHaveKey('device_id', 'abc-123');
});
