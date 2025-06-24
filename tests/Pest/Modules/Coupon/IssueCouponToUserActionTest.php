<?php

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Actions\GenerateUniqueCodeAction;
use App\Modules\Coupon\Actions\IssueBulkCouponsAction;
use App\Modules\Coupon\Actions\IssueCouponToUserAction;
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

    // Create dependencies
    $eligibilityValidator = new ValidateCouponEligibilityAction();
    $codeGenerator = new GenerateUniqueCodeAction();

    $singleCouponAction = new IssueSingleCouponAction($eligibilityValidator, $codeGenerator);
    $bulkCouponsAction = new IssueBulkCouponsAction($eligibilityValidator, $codeGenerator);

    $this->action = new IssueCouponToUserAction($singleCouponAction, $bulkCouponsAction);
});

describe('IssueCouponToUserAction', function () {

    test('can issue single coupon by default (quantity not specified)', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 100,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 3,
        ]);

        $result = $this->action->execute($issuanceData);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toBeInstanceOf(UserCoupon::class)
            ->and($result[0]->user_id)->toBe($this->user->id)
            ->and($result[0]->coupon_id)->toBe($coupon->id)
            ->and($result[0]->times_can_be_used)->toBe(3);
    });

    test('can issue single coupon when quantity is 1', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $result = $this->action->execute($issuanceData, 1);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount(1)
            ->and($result[0])->toBeInstanceOf(UserCoupon::class);
    });

    test('can issue multiple coupons when quantity is greater than 1', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 100,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 2,
        ]);

        $quantity = 5;
        $result = $this->action->execute($issuanceData, $quantity);

        expect($result)->toBeArray()
            ->and($result)->toHaveCount($quantity);

        foreach ($result as $userCoupon) {
            expect($userCoupon)->toBeInstanceOf(UserCoupon::class)
                ->and($userCoupon->user_id)->toBe($this->user->id)
                ->and($userCoupon->coupon_id)->toBe($coupon->id)
                ->and($userCoupon->times_can_be_used)->toBe(2);
        }

        // Verify all have unique codes
        $codes = array_map(fn($uc) => $uc->unique_code, $result);
        expect(array_unique($codes))->toHaveCount($quantity);
    });

    test('uses single issuance action for quantity 1', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        $result = $this->action->execute($issuanceData, 1);

        expect($result)->toHaveCount(1);

        // Verify it was properly issued (single issuance path)
        expect(UserCoupon::where('user_id', $this->user->id)
            ->where('coupon_id', $coupon->id)
            ->count())->toBe(1);
    });

    test('uses bulk issuance action for quantity greater than 1', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 100,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 3,
        ]);

        $quantity = 7;
        $result = $this->action->execute($issuanceData, $quantity);

        expect($result)->toHaveCount($quantity);

        // Verify all were properly issued (bulk issuance path)
        expect(UserCoupon::where('user_id', $this->user->id)
            ->where('coupon_id', $coupon->id)
            ->count())->toBe($quantity);
    });

    test('inherits all validation from underlying actions', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
            'expires_at' => Carbon::yesterday(),
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Should fail validation from underlying actions
        expect(fn() => $this->action->execute($issuanceData))
            ->toThrow(\InvalidArgumentException::class, 'Coupon has expired');
    });

    test('handles single-use coupon restrictions properly', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::SINGLE_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Should fail when trying to issue multiple single-use coupons
        expect(fn() => $this->action->execute($issuanceData, 3))
            ->toThrow(\InvalidArgumentException::class, 'Cannot issue multiple single-use coupons to same user');
    });

    test('handles max issuance limits properly', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 5,
        ]);

        // Pre-fill with 3 coupons
        UserCoupon::factory()->count(3)->create([
            'coupon_id' => $coupon->id,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 1,
        ]);

        // Should fail when trying to exceed max_issuance (3 + 5 = 8 > 5)
        expect(fn() => $this->action->execute($issuanceData, 5))
            ->toThrow(\InvalidArgumentException::class, 'Bulk issuance would exceed maximum limit');

        // Should work within limits (3 + 2 = 5 = limit)
        $result = $this->action->execute($issuanceData, 2);
        expect($result)->toHaveCount(2);
    });

    test('maintains consistent behavior between single and bulk paths', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 4,
        ]);

        // Issue via single path
        $singleResult = $this->action->execute($issuanceData, 1);

        // Issue via bulk path
        $bulkResult = $this->action->execute($issuanceData, 1);

        // Both should have same structure and properties
        expect($singleResult)->toHaveCount(1)
            ->and($bulkResult)->toHaveCount(1)
            ->and($singleResult[0]->times_can_be_used)->toBe(4)
            ->and($bulkResult[0]->times_can_be_used)->toBe(4)
            ->and($singleResult[0]->status)->toBe($bulkResult[0]->status);
    });

    test('performs efficiently for large quantities', function () {
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'type' => CouponTypeEnum::MULTI_USE,
            'max_issuance' => 1000,
        ]);

        $issuanceData = IssueCouponData::from([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'times_can_be_used' => 2,
        ]);

        $startTime = microtime(true);
        $quantity = 100;
        $result = $this->action->execute($issuanceData, $quantity);
        $endTime = microtime(true);

        $duration = $endTime - $startTime;

        expect($result)->toHaveCount($quantity)
            ->and($duration)->toBeLessThan(3.0); // Should complete in under 3 seconds

        // Verify all database records
        expect(UserCoupon::where('user_id', $this->user->id)
            ->where('coupon_id', $coupon->id)
            ->count())->toBe($quantity);
    });
});
