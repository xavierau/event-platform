<?php

use App\Modules\Coupon\Actions\FindUserCouponByCodeAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use App\Models\User;

describe('FindUserCouponByCodeAction', function () {
    beforeEach(function () {
        $this->action = new FindUserCouponByCodeAction();
        $this->user = User::factory()->create();
        $this->coupon = Coupon::factory()->create();
    });

    it('can find an active user coupon by unique code', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'TEST-COUPON-123',
            'status' => UserCouponStatusEnum::ACTIVE,
        ]);

        $result = $this->action->execute('TEST-COUPON-123');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($userCoupon->id)
            ->and($result->unique_code)->toBe('TEST-COUPON-123')
            ->and($result->status)->toBe(UserCouponStatusEnum::ACTIVE);
    });

    it('can find a fully used user coupon by unique code', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'USED-COUPON-456',
            'status' => UserCouponStatusEnum::FULLY_USED,
        ]);

        $result = $this->action->execute('USED-COUPON-456');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($userCoupon->id)
            ->and($result->status)->toBe(UserCouponStatusEnum::FULLY_USED);
    });

    it('can find an expired user coupon by unique code', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'EXPIRED-COUPON-789',
            'status' => UserCouponStatusEnum::EXPIRED,
        ]);

        $result = $this->action->execute('EXPIRED-COUPON-789');

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($userCoupon->id)
            ->and($result->status)->toBe(UserCouponStatusEnum::EXPIRED);
    });

    it('returns null when coupon code does not exist', function () {
        $result = $this->action->execute('NON-EXISTENT-CODE');

        expect($result)->toBeNull();
    });

    it('finds coupon with case sensitive unique code', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'CaseSensitive123',
            'status' => UserCouponStatusEnum::ACTIVE,
        ]);

        $result = $this->action->execute('CaseSensitive123');
        expect($result)->not->toBeNull();

        $result = $this->action->execute('casesensitive123');
        expect($result)->toBeNull();

        $result = $this->action->execute('CASESENSITIVE123');
        expect($result)->toBeNull();
    });

    it('loads related user and coupon data', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'WITH-RELATIONS-ABC',
            'status' => UserCouponStatusEnum::ACTIVE,
        ]);

        $result = $this->action->execute('WITH-RELATIONS-ABC');

        expect($result)->not->toBeNull()
            ->and($result->relationLoaded('user'))->toBeTrue()
            ->and($result->relationLoaded('coupon'))->toBeTrue()
            ->and($result->user->id)->toBe($this->user->id)
            ->and($result->coupon->id)->toBe($this->coupon->id);
    });

    it('handles empty or whitespace code gracefully', function () {
        expect($this->action->execute(''))->toBeNull();
        expect($this->action->execute('   '))->toBeNull();
        expect($this->action->execute("\t"))->toBeNull();
        expect($this->action->execute("\n"))->toBeNull();
    });

    it('handles special characters in code lookup', function () {
        $userCoupon = UserCoupon::factory()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $this->coupon->id,
            'unique_code' => 'CODE-WITH-SPECIAL!@#$%',
            'status' => UserCouponStatusEnum::ACTIVE,
        ]);

        $result = $this->action->execute('CODE-WITH-SPECIAL!@#$%');

        expect($result)->not->toBeNull()
            ->and($result->unique_code)->toBe('CODE-WITH-SPECIAL!@#$%');
    });
});
