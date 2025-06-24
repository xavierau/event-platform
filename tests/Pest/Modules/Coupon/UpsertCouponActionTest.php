<?php

use App\Models\Organizer;
use App\Modules\Coupon\Actions\UpsertCouponAction;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Models\Coupon;
use Database\Factories\Modules\Coupon\CouponFactory;

beforeEach(function () {
    $this->organizer = Organizer::factory()->create();
    $this->action = new UpsertCouponAction();
});

describe('UpsertCouponAction', function () {

    test('can create a new coupon with valid data', function () {
        $couponData = CouponData::from([
            'organizer_id' => $this->organizer->id,
            'name' => 'Summer Sale',
            'description' => 'Great summer discounts',
            'code' => 'SUMMER2024',
            'type' => CouponTypeEnum::SINGLE_USE,
            'discount_value' => 1500, // $15.00
            'discount_type' => 'fixed',
            'max_issuance' => 100,
            'valid_from' => '2024-06-01',
            'expires_at' => '2024-08-31',
        ]);

        $coupon = $this->action->execute($couponData);

        expect($coupon)->toBeInstanceOf(Coupon::class)
            ->and($coupon->id)->not->toBeNull()
            ->and($coupon->organizer_id)->toBe($this->organizer->id)
            ->and($coupon->name)->toBe('Summer Sale')
            ->and($coupon->description)->toBe('Great summer discounts')
            ->and($coupon->code)->toBe('SUMMER2024')
            ->and($coupon->type)->toBe(CouponTypeEnum::SINGLE_USE)
            ->and($coupon->discount_value)->toBe(1500)
            ->and($coupon->discount_type)->toBe('fixed')
            ->and($coupon->max_issuance)->toBe(100);

        expect($coupon->valid_from->format('Y-m-d'))->toBe('2024-06-01')
            ->and($coupon->expires_at->format('Y-m-d'))->toBe('2024-08-31');

        // Verify it was persisted to database
        $this->assertDatabaseHas('coupons', [
            'id' => $coupon->id,
            'code' => 'SUMMER2024',
            'organizer_id' => $this->organizer->id,
        ]);
    });

    test('can update an existing coupon', function () {
        // Create initial coupon
        $existingCoupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'name' => 'Old Name',
            'code' => 'OLDCODE',
            'discount_value' => 1000,
        ]);

        $couponData = CouponData::from([
            'id' => $existingCoupon->id,
            'organizer_id' => $this->organizer->id,
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'code' => 'NEWCODE',
            'type' => CouponTypeEnum::MULTI_USE,
            'discount_value' => 2000,
            'discount_type' => 'percentage',
            'max_issuance' => 200,
            'valid_from' => '2024-07-01',
            'expires_at' => '2024-09-30',
        ]);

        $updatedCoupon = $this->action->execute($couponData);

        expect($updatedCoupon->id)->toBe($existingCoupon->id)
            ->and($updatedCoupon->name)->toBe('Updated Name')
            ->and($updatedCoupon->code)->toBe('NEWCODE')
            ->and($updatedCoupon->discount_value)->toBe(2000)
            ->and($updatedCoupon->type)->toBe(CouponTypeEnum::MULTI_USE);

        // Verify update was persisted
        $this->assertDatabaseHas('coupons', [
            'id' => $existingCoupon->id,
            'name' => 'Updated Name',
            'code' => 'NEWCODE',
        ]);
    });

    test('can create coupon with minimal required data', function () {
        $couponData = CouponData::from([
            'organizer_id' => $this->organizer->id,
            'name' => 'Basic Coupon',
            'description' => null,
            'code' => 'BASIC2024',
            'type' => CouponTypeEnum::SINGLE_USE,
            'discount_value' => 500,
            'discount_type' => 'fixed',
            'max_issuance' => null,
            'valid_from' => null,
            'expires_at' => null,
        ]);

        $coupon = $this->action->execute($couponData);

        expect($coupon)->toBeInstanceOf(Coupon::class)
            ->and($coupon->description)->toBeNull()
            ->and($coupon->max_issuance)->toBeNull()
            ->and($coupon->valid_from)->toBeNull()
            ->and($coupon->expires_at)->toBeNull();
    });

    test('can create multi-use coupon', function () {
        $couponData = CouponData::from([
            'organizer_id' => $this->organizer->id,
            'name' => 'Multi Use Coupon',
            'description' => 'Can be used multiple times',
            'code' => 'MULTI2024',
            'type' => CouponTypeEnum::MULTI_USE,
            'discount_value' => 1000,
            'discount_type' => 'percentage',
            'max_issuance' => 50,
            'valid_from' => '2024-01-01',
            'expires_at' => '2024-12-31',
        ]);

        $coupon = $this->action->execute($couponData);

        expect($coupon->type)->toBe(CouponTypeEnum::MULTI_USE)
            ->and($coupon->discount_type)->toBe('percentage');
    });

    test('handles percentage discount type correctly', function () {
        $couponData = CouponData::from([
            'organizer_id' => $this->organizer->id,
            'name' => 'Percentage Discount',
            'description' => '10% off',
            'code' => 'PERCENT10',
            'type' => CouponTypeEnum::SINGLE_USE,
            'discount_value' => 10, // 10%
            'discount_type' => 'percentage',
            'max_issuance' => 100,
            'valid_from' => null,
            'expires_at' => null,
        ]);

        $coupon = $this->action->execute($couponData);

        expect($coupon->discount_type)->toBe('percentage')
            ->and($coupon->discount_value)->toBe(10);
    });

    test('preserves organizer relationship', function () {
        $couponData = CouponData::from([
            'organizer_id' => $this->organizer->id,
            'name' => 'Organizer Test',
            'description' => null,
            'code' => 'ORG2024',
            'type' => CouponTypeEnum::SINGLE_USE,
            'discount_value' => 1000,
            'discount_type' => 'fixed',
            'max_issuance' => null,
            'valid_from' => null,
            'expires_at' => null,
        ]);

        $coupon = $this->action->execute($couponData);

        expect($coupon->organizer)->toBeInstanceOf(Organizer::class)
            ->and($coupon->organizer->id)->toBe($this->organizer->id);
    });
});
