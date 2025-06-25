<?php

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Actions\GenerateUniqueCodeAction;
use App\Modules\Coupon\Actions\IssueBulkCouponsAction;
use App\Modules\Coupon\Actions\ValidateCouponEligibilityAction;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use Carbon\Carbon;

beforeEach(function () {
    $this->organizer = Organizer::factory()->create();
    $this->user = User::factory()->create();
    $this->action = new IssueBulkCouponsAction(
        new ValidateCouponEligibilityAction(),
        new GenerateUniqueCodeAction()
    );
});

describe('IssueBulkCouponsAction', function () {

    test('can issue multiple coupons to single user', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 100,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 3,
        ]);

        $quantity = 5;
        $userCoupons = $this->action->execute($issuanceData, $quantity);

        expect($userCoupons)->toBeArray()
            ->and($userCoupons)->toHaveCount($quantity);

        foreach ($userCoupons as $userCoupon) {
            expect($userCoupon)->toBeInstanceOf(UserCoupon::class)
                ->and($userCoupon->user_id)->toBe($this->user->id)
                ->and($userCoupon->coupon_id)->toBe($coupon->id)
                ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE)
                ->and($userCoupon->times_can_be_used)->toBe(3)
                ->and($userCoupon->times_used)->toBe(0)
                ->and($userCoupon->unique_code)->not->toBeNull()
                ->and($userCoupon->unique_code)->toHaveLength(12);
        }

        // Verify all have unique codes
        $codes = array_map(fn($uc) => $uc->unique_code, $userCoupons);
        expect(array_unique($codes))->toHaveCount($quantity);

        // Verify all were persisted to database
        expect(UserCoupon::where('user_id', $this->user->id)
            ->where('coupon_id', $coupon->id)
            ->count())->toBe($quantity);
    });

    test('can issue single coupon in bulk (quantity 1)', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupons = $this->action->execute($issuanceData, 1);

        expect($userCoupons)->toHaveCount(1)
            ->and($userCoupons[0])->toBeInstanceOf(UserCoupon::class);
    });

    test('preserves individual coupon expiry dates', function () {
        $expiryDate = Carbon::now()->addWeek();
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'expires_at' => $expiryDate,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupons = $this->action->execute($issuanceData, 3);

        foreach ($userCoupons as $userCoupon) {
            expect($userCoupon->expires_at->format('Y-m-d H:i'))
                ->toBe($expiryDate->format('Y-m-d H:i'));
        }
    });

    test('throws exception when trying to issue more than max_issuance allows', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 10,
        ]);

        // Already have 8 issued coupons
        UserCoupon::factory()->count(8)->create([
            'coupon_id' => $coupon->id,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Trying to issue 5 more would exceed the limit (8 + 5 = 13 > 10)
        expect(fn() => $this->action->execute($issuanceData, 5))
            ->toThrow(\InvalidArgumentException::class, 'Bulk issuance would exceed maximum limit');
    });

    test('allows bulk issuance within max_issuance limit', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 10,
        ]);

        // Already have 7 issued coupons
        UserCoupon::factory()->count(7)->create([
            'coupon_id' => $coupon->id,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Issuing 3 more should work (7 + 3 = 10 = limit)
        $userCoupons = $this->action->execute($issuanceData, 3);

        expect($userCoupons)->toHaveCount(3);
    });

    test('throws exception for single-use coupon when user already has one', function () {
<<<<<<< HEAD
        $coupon = Coupon::factory()->create([
=======
        $coupon = Coupon::factory()->withValidPeriod()->create([
>>>>>>> feature/coupon-module
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        // User already has this coupon
        UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

<<<<<<< HEAD
        expect(fn() => $this->action->execute($issuanceData, 2))
=======
        expect(fn() => $this->action->execute($issuanceData, 1))
>>>>>>> feature/coupon-module
            ->toThrow(\InvalidArgumentException::class, 'User already has this single-use coupon');
    });

    test('throws exception when trying to issue single-use coupon multiple times', function () {
<<<<<<< HEAD
        $coupon = Coupon::factory()->create([
=======
        $coupon = Coupon::factory()->withValidPeriod()->create([
>>>>>>> feature/coupon-module
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Can't issue multiple single-use coupons to same user
        expect(fn() => $this->action->execute($issuanceData, 3))
            ->toThrow(\InvalidArgumentException::class, 'Cannot issue multiple single-use coupons to same user');
    });

    test('allows single-use coupon bulk issuance of quantity 1', function () {
<<<<<<< HEAD
        $coupon = Coupon::factory()->create([
=======
        $coupon = Coupon::factory()->withValidPeriod()->create([
>>>>>>> feature/coupon-module
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupons = $this->action->execute($issuanceData, 1);

        expect($userCoupons)->toHaveCount(1)
            ->and($userCoupons[0]->coupon->type)->toBe(CouponTypeEnum::SINGLE_USE);
    });

    test('throws exception for invalid quantity', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        expect(fn() => $this->action->execute($issuanceData, 0))
            ->toThrow(\InvalidArgumentException::class, 'Quantity must be at least 1');

        expect(fn() => $this->action->execute($issuanceData, -1))
            ->toThrow(\InvalidArgumentException::class, 'Quantity must be at least 1');
    });

    test('performance test - can issue large quantities efficiently', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 1000,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 5,
        ]);

        $startTime = microtime(true);
        $quantity = 50;
        $userCoupons = $this->action->execute($issuanceData, $quantity);
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        expect($userCoupons)->toHaveCount($quantity)
            ->and($duration)->toBeLessThan(2.0); // Should complete in under 2 seconds

        // Verify all have unique codes
        $codes = array_map(fn($uc) => $uc->unique_code, $userCoupons);
        expect(array_unique($codes))->toHaveCount($quantity);
    });
});
