<?php

namespace Tests\Pest\Modules\Coupon\Models;

use App\Models\Organizer;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;

it('can create a coupon', function () {
    $organizer = Organizer::factory()->create();
    $coupon = Coupon::factory()->create([
        'organizer_id' => $organizer->id,
    ]);

    expect($coupon)->toBeInstanceOf(Coupon::class);
    expect($coupon->organizer)->toBeInstanceOf(Organizer::class);
});

it('casts the type attribute to CouponTypeEnum', function () {
    $coupon = Coupon::factory()->create(['type' => CouponTypeEnum::SINGLE_USE]);

    expect($coupon->type)->toBeInstanceOf(CouponTypeEnum::class)
        ->and($coupon->type)->toBe(CouponTypeEnum::SINGLE_USE);
});

it('has many user coupons', function () {
    $coupon = Coupon::factory()->create();
    UserCoupon::factory()->count(3)->create(['coupon_id' => $coupon->id]);

    expect($coupon->userCoupons)->toHaveCount(3)
        ->each->toBeInstanceOf(UserCoupon::class);
});

it('creates a coupon with correct default and casted values', function () {
    $organizer = Organizer::factory()->create();
    $coupon = Coupon::factory()->create([
        'organizer_id' => $organizer->id,
        'type' => 'multi_use',
        'discount_type' => 'percentage',
        'discount_value' => 15,
        'expires_at' => '2025-12-31 23:59:59',
    ]);

    $coupon->refresh();

    expect($coupon->type)->toBeInstanceOf(CouponTypeEnum::class)
        ->and($coupon->type->value)->toBe('multi_use')
        ->and($coupon->discount_type)->toBe('percentage')
        ->and($coupon->discount_value)->toBe(15)
        ->and($coupon->expires_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
