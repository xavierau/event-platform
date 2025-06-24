<?php

use App\Models\Organizer;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
use App\Modules\Coupon\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->organizer = Organizer::factory()->create();
});

describe('PIN Redemption Infrastructure', function () {

    describe('RedemptionMethodEnum', function () {
        test('has correct QR case', function () {
            expect(RedemptionMethodEnum::QR->value)->toBe('qr');
        });

        test('has correct PIN case', function () {
            expect(RedemptionMethodEnum::PIN->value)->toBe('pin');
        });

        test('can be used in validation', function () {
            $validValues = ['qr', 'pin'];

            expect(RedemptionMethodEnum::QR->value)->toBeIn($validValues)
                ->and(RedemptionMethodEnum::PIN->value)->toBeIn($validValues);
        });
    });

    describe('Coupon Model - PIN Support', function () {
        test('can create coupon with QR-only redemption method', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['qr'],
                'merchant_pin' => null,
            ]);

            expect($coupon->redemption_methods)->toBe(['qr'])
                ->and($coupon->merchant_pin)->toBeNull();
        });

        test('can create coupon with PIN-only redemption method', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['pin'],
                'merchant_pin' => '123456',
            ]);

            expect($coupon->redemption_methods)->toBe(['pin'])
                ->and($coupon->merchant_pin)->toBe('123456');
        });

        test('can create coupon with both QR and PIN redemption methods', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['qr', 'pin'],
                'merchant_pin' => '654321',
            ]);

            expect($coupon->redemption_methods)->toBe(['qr', 'pin'])
                ->and($coupon->merchant_pin)->toBe('654321');
        });

        test('redemption_methods is properly cast to array', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['qr', 'pin'],
            ]);

            // Refresh from database to test casting
            $coupon = $coupon->fresh();

            expect($coupon->redemption_methods)->toBeArray()
                ->and($coupon->redemption_methods)->toContain('qr', 'pin');
        });

        test('defaults to QR redemption method when not specified', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                // Don't specify redemption_methods to test default
            ]);

            expect($coupon->redemption_methods)->toBe(['qr']);
        });
    });

    describe('CouponData DTO - PIN Validation', function () {
        test('validates QR-only redemption method successfully', function () {
            $data = CouponData::from([
                'organizer_id' => $this->organizer->id,
                'name' => 'Test Coupon',
                'description' => 'Test Description',
                'code' => 'TEST123',
                'type' => CouponTypeEnum::SINGLE_USE,
                'discount_value' => 100,
                'discount_type' => 'fixed',
                'max_issuance' => 50,
                'valid_from' => now()->toDateTimeString(),
                'expires_at' => now()->addMonth()->toDateTimeString(),
                'redemption_methods' => ['qr'],
                'merchant_pin' => null,
            ]);

            expect($data->redemption_methods)->toBe(['qr'])
                ->and($data->merchant_pin)->toBeNull();
        });

        test('validates PIN-only redemption method with PIN successfully', function () {
            $data = CouponData::from([
                'organizer_id' => $this->organizer->id,
                'name' => 'Test Coupon',
                'description' => 'Test Description',
                'code' => 'TEST456',
                'type' => CouponTypeEnum::SINGLE_USE,
                'discount_value' => 100,
                'discount_type' => 'fixed',
                'max_issuance' => 50,
                'valid_from' => now()->toDateTimeString(),
                'expires_at' => now()->addMonth()->toDateTimeString(),
                'redemption_methods' => ['pin'],
                'merchant_pin' => '123456',
            ]);

            expect($data->redemption_methods)->toBe(['pin'])
                ->and($data->merchant_pin)->toBe('123456');
        });

        test('validates both QR and PIN redemption methods successfully', function () {
            $data = CouponData::from([
                'organizer_id' => $this->organizer->id,
                'name' => 'Test Coupon',
                'description' => 'Test Description',
                'code' => 'TEST789',
                'type' => CouponTypeEnum::SINGLE_USE,
                'discount_value' => 100,
                'discount_type' => 'fixed',
                'max_issuance' => 50,
                'valid_from' => now()->toDateTimeString(),
                'expires_at' => now()->addMonth()->toDateTimeString(),
                'redemption_methods' => ['qr', 'pin'],
                'merchant_pin' => '654321',
            ]);

            expect($data->redemption_methods)->toBe(['qr', 'pin'])
                ->and($data->merchant_pin)->toBe('654321');
        });

        test('uses default QR redemption method when not specified', function () {
            $data = CouponData::from([
                'organizer_id' => $this->organizer->id,
                'name' => 'Test Coupon',
                'description' => 'Test Description',
                'code' => 'TESTDEF',
                'type' => CouponTypeEnum::SINGLE_USE,
                'discount_value' => 100,
                'discount_type' => 'fixed',
                'max_issuance' => 50,
                'valid_from' => now()->toDateTimeString(),
                'expires_at' => now()->addMonth()->toDateTimeString(),
                // Don't specify redemption_methods to test default
            ]);

            expect($data->redemption_methods)->toBe(['qr'])
                ->and($data->merchant_pin)->toBeNull();
        });
    });

    describe('Database Schema Validation', function () {
        test('coupons table has redemption_methods column', function () {
            $columns = Schema::getColumnListing('coupons');

            expect($columns)->toContain('redemption_methods');
        });

        test('coupons table has merchant_pin column', function () {
            $columns = Schema::getColumnListing('coupons');

            expect($columns)->toContain('merchant_pin');
        });

        test('can store and retrieve JSON redemption_methods', function () {
            $coupon = Coupon::factory()->create([
                'organizer_id' => $this->organizer->id,
                'redemption_methods' => ['qr', 'pin'],
                'merchant_pin' => '123456',
            ]);

            // Verify it was stored correctly in the database
            $this->assertDatabaseHas('coupons', [
                'id' => $coupon->id,
                'merchant_pin' => '123456',
            ]);

            // Verify JSON structure (need to check raw value)
            $rawCoupon = DB::table('coupons')->where('id', $coupon->id)->first();
            $storedMethods = json_decode($rawCoupon->redemption_methods, true);

            expect($storedMethods)->toBe(['qr', 'pin']);
        });
    });
});
