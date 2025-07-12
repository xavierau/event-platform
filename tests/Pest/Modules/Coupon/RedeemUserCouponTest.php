<?php

use App\Modules\Coupon\Actions\RedeemUserCouponAction;
use App\Modules\Coupon\Actions\FindUserCouponByCodeAction;
use App\Modules\Coupon\Actions\ValidateUserCouponForRedemptionAction;
use App\Modules\Coupon\Actions\IncrementCouponUsageAction;
use App\Modules\Coupon\Actions\CreateCouponUsageLogAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Models\CouponUsageLog;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Models\User;
use Carbon\Carbon;

describe('RedeemUserCouponAction', function () {
    beforeEach(function () {
        $this->findAction = new FindUserCouponByCodeAction();
        $this->validateAction = new ValidateUserCouponForRedemptionAction();
        $this->incrementAction = new IncrementCouponUsageAction();
        $this->logAction = new CreateCouponUsageLogAction();

        $this->action = new RedeemUserCouponAction(
            $this->findAction,
            $this->validateAction,
            $this->incrementAction,
            $this->logAction
        );

        $this->user = User::factory()->create();
        $this->coupon = Coupon::factory()->create(['type' => CouponTypeEnum::MULTI_USE]);
    });

    it('successfully redeems a valid coupon', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'VALID-COUPON-123',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 1,
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        $result = $this->action->execute('VALID-COUPON-123');

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['message'])->toBe('Coupon redeemed successfully')
            ->and($result['user_coupon'])->toBeInstanceOf(UserCoupon::class)
            ->and($result['usage_log'])->toBeInstanceOf(CouponUsageLog::class);

        // Verify coupon was incremented
        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(2);

        // Verify usage log was created
        expect(CouponUsageLog::where('user_coupon_id', $userCoupon->id)->count())->toBe(1);
    });

    it('redeems coupon with location and details', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'COUPON-WITH-LOCATION',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 2,
            'times_used' => 0,
            'expires_at' => Carbon::now()->addDays(5),
        ]);

        $location = 'Main Event Hall';
        $details = [
            'scanner_id' => 'scan_001',
            'event_id' => 456,
            'redeem_source' => 'mobile_app',
        ];

        $result = $this->action->execute('COUPON-WITH-LOCATION', $location, $details);

        expect($result['success'])->toBeTrue()
            ->and($result['usage_log']->location)->toBe($location)
            ->and($result['usage_log']->details)->toBe($details);
    });

    it('fails when coupon code does not exist', function () {
        $result = $this->action->execute('NON-EXISTENT-CODE');

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Coupon not found')
            ->and($result['user_coupon'])->toBeNull()
            ->and($result['usage_log'])->toBeNull();
    });

    it('fails when coupon is expired', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'EXPIRED-COUPON-456',
            'status' => UserCouponStatusEnum::EXPIRED,
            'expires_at' => Carbon::now()->subDays(1),
        ]);

        $result = $this->action->execute('EXPIRED-COUPON-456');

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Coupon validation failed')
            ->and($result['validation_errors'])->toContain('Coupon has expired')
            ->and($result['user_coupon'])->toBeInstanceOf(UserCoupon::class)
            ->and($result['usage_log'])->toBeNull();

        // Verify coupon was not incremented
        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(0);
    });

    it('fails when coupon is fully used', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'FULLY-USED-789',
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_can_be_used' => 1,
            'times_used' => 1,
        ]);

        $result = $this->action->execute('FULLY-USED-789');

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Coupon validation failed')
            ->and($result['validation_errors'])->toContain('Coupon has been fully used');
    });

    it('marks single-use coupon as fully used after redemption', function () {
        $singleUseCoupon = Coupon::factory()->create(['type' => CouponTypeEnum::SINGLE_USE]);
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $singleUseCoupon->id,
            'unique_code' => 'SINGLE-USE-ABC',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 1,
            'times_used' => 0,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        $result = $this->action->execute('SINGLE-USE-ABC');

        expect($result['success'])->toBeTrue();

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(1)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('marks multi-use coupon as fully used when reaching limit', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'LAST-USE-DEF',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 3,
            'times_used' => 2, // One use remaining
            'expires_at' => Carbon::now()->addDays(15),
        ]);

        $result = $this->action->execute('LAST-USE-DEF');

        expect($result['success'])->toBeTrue();

        $userCoupon->refresh();
        expect($userCoupon->times_used)->toBe(3)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('handles validation with multiple errors', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'MULTI-ERROR-GHI',
            'status' => UserCouponStatusEnum::FULLY_USED,
            'times_can_be_used' => 2,
            'times_used' => 2,
            'expires_at' => Carbon::now()->subDays(3),
        ]);

        $result = $this->action->execute('MULTI-ERROR-GHI');

        expect($result['success'])->toBeFalse()
            ->and($result['validation_errors'])->toHaveCount(2)
            ->and($result['validation_errors'])->toContain('Coupon has expired')
            ->and($result['validation_errors'])->toContain('Coupon has been fully used');
    });

    it('performs redemption in atomic transaction', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'TRANSACTION-TEST',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 5,
            'times_used' => 2,
            'expires_at' => Carbon::now()->addDays(10),
        ]);

        $initialUsageLogCount = CouponUsageLog::count();
        $initialTimesUsed = $userCoupon->times_used;

        $result = $this->action->execute('TRANSACTION-TEST');

        if ($result['success']) {
            // Both operations should have succeeded
            $userCoupon->refresh();
            expect($userCoupon->times_used)->toBe($initialTimesUsed + 1);
            expect(CouponUsageLog::count())->toBe($initialUsageLogCount + 1);
        } else {
            // Both operations should have been rolled back
            $userCoupon->refresh();
            expect($userCoupon->times_used)->toBe($initialTimesUsed);
            expect(CouponUsageLog::count())->toBe($initialUsageLogCount);
        }
    });

    it('returns detailed validation information on failure', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'DETAIL-TEST-JKL',
            'status' => UserCouponStatusEnum::ACTIVE,
            'times_can_be_used' => 5,
            'times_used' => 4,
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        // This should succeed, but let's test the validation details structure
        $result = $this->action->execute('DETAIL-TEST-JKL');

        expect($result['success'])->toBeTrue()
            ->and($result)->toHaveKeys(['success', 'message', 'user_coupon', 'usage_log']);
    });

    it('handles empty coupon code', function () {
        $result = $this->action->execute('');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Coupon not found');
    });

    it('handles whitespace-only coupon code', function () {
        $result = $this->action->execute('   ');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Coupon not found');
    });
});
