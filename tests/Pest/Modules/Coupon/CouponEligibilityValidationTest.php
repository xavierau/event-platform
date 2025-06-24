<?php

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Actions\ValidateCouponEligibilityAction;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Carbon\Carbon;

beforeEach(function () {
    $this->organizer = Organizer::factory()->create();
    $this->user = User::factory()->create();
    $this->action = new ValidateCouponEligibilityAction();
});

describe('ValidateCouponEligibilityAction', function () {

    test('validates successfully for eligible user and valid coupon', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => 100,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });

    test('rejects when coupon has not started yet', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'valid_from' => Carbon::tomorrow(),
            'expires_at' => Carbon::now()->addWeek(),
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeFalse()
            ->and($result['reason'])->toBe('Coupon is not yet valid');
    });

    test('rejects when coupon has expired', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'valid_from' => Carbon::now()->subWeek(),
            'expires_at' => Carbon::yesterday(),
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeFalse()
            ->and($result['reason'])->toBe('Coupon has expired');
    });

    test('rejects when max issuance limit reached', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => 2,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        // Create 2 issued coupons to reach the limit
        UserCoupon::factory()->count(2)->create([
            'coupon_id' => $coupon->id,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeFalse()
            ->and($result['reason'])->toBe('Maximum issuance limit reached');
    });

    test('allows issuance when under max issuance limit', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => 5,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        // Create 3 issued coupons (under the limit of 5)
        UserCoupon::factory()->count(3)->create([
            'coupon_id' => $coupon->id,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });

    test('allows issuance when max_issuance is null (unlimited)', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => null, // Unlimited
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        // Create many issued coupons
        UserCoupon::factory()->count(1000)->create([
            'coupon_id' => $coupon->id,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });

    test('allows issuance when valid_from is null', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'valid_from' => null,
            'expires_at' => Carbon::tomorrow(),
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });

    test('allows issuance when expires_at is null', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => null,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });

    test('rejects if user already has same single-use coupon', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
            'max_issuance' => 100,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        // User already has this coupon
        UserCoupon::factory()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeFalse()
            ->and($result['reason'])->toBe('User already has this single-use coupon');
    });

    test('allows multiple issuance for multi-use coupons to same user', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 100,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        // User already has this multi-use coupon
        UserCoupon::factory()->create([
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
        ]);

        $result = $this->action->execute($coupon, $this->user);

        expect($result)->toBeArray()
            ->and($result['eligible'])->toBeTrue()
            ->and($result['reason'])->toBeNull();
    });
});
