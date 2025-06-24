<?php

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Actions\GenerateUniqueCodeAction;
use App\Modules\Coupon\Actions\IssueSingleCouponAction;
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
    $this->action = new IssueSingleCouponAction(
        new ValidateCouponEligibilityAction(),
        new GenerateUniqueCodeAction()
    );
});

describe('IssueSingleCouponAction', function () {

    test('can issue single-use coupon to user successfully', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
            'max_issuance' => 100,
            'valid_from' => Carbon::yesterday(),
            'expires_at' => Carbon::tomorrow(),
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupon = $this->action->execute($issuanceData);

        expect($userCoupon)->toBeInstanceOf(UserCoupon::class)
            ->and($userCoupon->user_id)->toBe($this->user->id)
            ->and($userCoupon->coupon_id)->toBe($coupon->id)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE)
            ->and($userCoupon->times_can_be_used)->toBe(1)
            ->and($userCoupon->times_used)->toBe(0)
            ->and($userCoupon->unique_code)->not->toBeNull()
            ->and($userCoupon->unique_code)->toHaveLength(12)
            ->and($userCoupon->issued_at)->not->toBeNull();

        // Verify it was persisted to database
        $this->assertDatabaseHas('user_coupons', [
            'id' => $userCoupon->id,
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'status' => UserCouponStatusEnum::ACTIVE->value,
        ]);
    });

    test('can issue multi-use coupon to user successfully', function () {
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
            'times_can_be_used' => 5, // Multi-use with 5 uses
        ]);

        $userCoupon = $this->action->execute($issuanceData);

        expect($userCoupon)->toBeInstanceOf(UserCoupon::class)
            ->and($userCoupon->times_can_be_used)->toBe(5)
            ->and($userCoupon->status)->toBe(UserCouponStatusEnum::ACTIVE);
    });

    test('sets correct expiry date when coupon has expires_at', function () {
        $expiryDate = Carbon::now()->addWeek();
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'expires_at' => $expiryDate,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupon = $this->action->execute($issuanceData);

        expect($userCoupon->expires_at->format('Y-m-d H:i'))
            ->toBe($expiryDate->format('Y-m-d H:i'));
    });

    test('sets null expiry when coupon has no expires_at', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'expires_at' => null,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupon = $this->action->execute($issuanceData);

        expect($userCoupon->expires_at)->toBeNull();
    });

    test('generates unique code for each issuance', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
        ]);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $issuanceData1 = IssueCouponData::from([
            'user_id' => $user1->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $issuanceData2 = IssueCouponData::from([
            'user_id' => $user2->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $userCoupon1 = $this->action->execute($issuanceData1);
        $userCoupon2 = $this->action->execute($issuanceData2);

        expect($userCoupon1->unique_code)->not->toBe($userCoupon2->unique_code);
    });

    test('throws exception when trying to issue single-use coupon to user who already has it', function () {
        $coupon = Coupon::factory()->create([
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

        expect(fn() => $this->action->execute($issuanceData))
            ->toThrow(\InvalidArgumentException::class, 'User already has this single-use coupon');
    });

    test('throws exception when coupon has reached max issuance limit', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'max_issuance' => 2,
        ]);

        // Create 2 issued coupons to reach the limit
        UserCoupon::factory()->count(2)->create([
            'coupon_id' => $coupon->id,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        expect(fn() => $this->action->execute($issuanceData))
            ->toThrow(\InvalidArgumentException::class, 'Maximum issuance limit reached');
    });

    test('throws exception when coupon is not yet valid', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'valid_from' => Carbon::tomorrow(),
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        expect(fn() => $this->action->execute($issuanceData))
            ->toThrow(\InvalidArgumentException::class, 'Coupon is not yet valid');
    });

    test('throws exception when coupon has expired', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'expires_at' => Carbon::yesterday(),
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        expect(fn() => $this->action->execute($issuanceData))
            ->toThrow(\InvalidArgumentException::class, 'Coupon has expired');
    });

    test('allows multi-use coupon to be issued multiple times to same user', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 3,
        ]);

        // Issue first coupon
        $userCoupon1 = $this->action->execute($issuanceData);

        // Issue second coupon to same user (should work for multi-use)
        $userCoupon2 = $this->action->execute($issuanceData);

        expect($userCoupon1->id)->not->toBe($userCoupon2->id)
            ->and($userCoupon1->unique_code)->not->toBe($userCoupon2->unique_code);

        // Verify both are in database
        expect(UserCoupon::where('user_id', $this->user->id)
            ->where('coupon_id', $coupon->id)
            ->count())->toBe(2);
    });
});
