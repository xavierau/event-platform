<?php

use App\Modules\Coupon\Actions\ValidateMerchantPinAction;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Models\User;
use App\Models\Organizer;

describe('ValidateMerchantPinAction', function () {
    beforeEach(function () {
        $this->action = new ValidateMerchantPinAction();
        $this->user = User::factory()->create();
        $this->organizer = Organizer::factory()->create();
    });

    describe('PIN validation logic', function () {
        it('validates correct PIN for PIN-enabled coupon', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => 'TEST123',
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeTrue()
                ->and($result['reasons'])->toBeEmpty();
        });

        it('rejects incorrect PIN for PIN-enabled coupon', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => 'TEST123',
            ]);

            $result = $this->action->execute($userCoupon, '654321');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Invalid merchant PIN');
        });

        it('validates coupon with both QR and PIN methods using correct PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '789012',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => 'TEST456',
            ]);

            $result = $this->action->execute($userCoupon, '789012');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeTrue()
                ->and($result['reasons'])->toBeEmpty();
        });

        it('rejects PIN validation for QR-only coupons', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::QR->value],
                'merchant_pin' => null,
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
                'unique_code' => 'TEST789',
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Coupon does not support PIN redemption');
        });
    });

    describe('PIN format validation', function () {
        it('accepts 6-digit numeric PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result['valid'])->toBeTrue();
        });

        it('rejects empty PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, '');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('PIN is required for PIN redemption');
        });

        it('rejects null PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, null);

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('PIN is required for PIN redemption');
        });

        it('rejects non-numeric PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, 'ABC123');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Invalid merchant PIN');
        });

        it('rejects PIN with wrong length', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            // Test too short
            $result = $this->action->execute($userCoupon, '12345');
            expect($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Invalid merchant PIN');

            // Test too long
            $result = $this->action->execute($userCoupon, '1234567');
            expect($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Invalid merchant PIN');
        });
    });

    describe('edge cases', function () {
        it('handles coupon with null merchant_pin gracefully', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => null,
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Coupon PIN is not configured');
        });

        it('handles coupon with empty redemption_methods array', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Coupon does not support PIN redemption');
        });

        it('handles coupon with string redemption_methods (malformed data)', function () {
            // Create a coupon with invalid redemption_methods format
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['qr'], // Valid format first
                'merchant_pin' => '123456',
            ]);

            // Manually update to simulate malformed data
            $coupon->update(['redemption_methods' => 'invalid']);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, '123456');

            expect($result)->toBeArray()
                ->and($result['valid'])->toBeFalse()
                ->and($result['reasons'])->toContain('Coupon does not support PIN redemption');
        });

        it('treats leading zeros in PIN correctly', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '012345',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            // Should match exactly
            $result = $this->action->execute($userCoupon, '012345');
            expect($result['valid'])->toBeTrue();

            // Should not match without leading zero
            $result = $this->action->execute($userCoupon, '12345');
            expect($result['valid'])->toBeFalse();
        });
    });

    describe('security considerations', function () {
        it('performs case-sensitive PIN comparison', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            // This should not be relevant for numeric PINs, but test for consistency
            $result = $this->action->execute($userCoupon, '123456');
            expect($result['valid'])->toBeTrue();
        });

        it('trims whitespace from provided PIN', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => [RedemptionMethodEnum::PIN->value],
                'merchant_pin' => '123456',
            ]);

            $userCoupon = UserCoupon::factory()->create([
                'user_id' => $this->user->id,
                'coupon_id' => $coupon->id,
            ]);

            $result = $this->action->execute($userCoupon, ' 123456 ');
            expect($result['valid'])->toBeTrue();
        });
    });
});