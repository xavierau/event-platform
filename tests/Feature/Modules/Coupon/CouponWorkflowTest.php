<?php

namespace Tests\Feature\Modules\Coupon;

use App\Models\Organizer;
use App\Models\User;
use App\Modules\Coupon\Services\CouponService;
use App\Modules\Coupon\DataTransferObjects\CouponData;
use App\Modules\Coupon\DataTransferObjects\IssueCouponData;
use App\Modules\Coupon\Enums\CouponTypeEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for end-to-end coupon workflows
 * Tests complete user journeys: issue → validate → redeem
 */
class CouponWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;
    private Organizer $organizer;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->couponService = app(CouponService::class);
        $this->organizer = Organizer::factory()->create();
        $this->user = User::factory()->create();
    }

    public function test_complete_single_use_coupon_workflow()
    {
        // Step 1: Create coupon template
        $couponData = new CouponData(
            organizer_id: $this->organizer->id,
            name: 'Feature Test Coupon',
            description: 'End-to-end workflow test',
            code: 'E2E_SINGLE',
            type: CouponTypeEnum::SINGLE_USE,
            discount_value: 1500,
            discount_type: 'fixed',
            max_issuance: 100,
            valid_from: now()->subDay()->format('Y-m-d H:i:s'),
            expires_at: now()->addMonth()->format('Y-m-d H:i:s'),
        );

        $coupon = $this->couponService->upsertCoupon($couponData);

        // Step 2: Issue coupon to user
        $issuanceData = new IssueCouponData(
            coupon_id: $coupon->id,
            user_id: $this->user->id,
            times_can_be_used: 1,
            quantity: 1
        );

        $issuedCoupons = $this->couponService->issueCoupon($issuanceData);
        $userCoupon = $issuedCoupons[0];

        // Step 3: User validates their coupon
        $validatedCoupon = $this->couponService->validateCoupon($userCoupon->unique_code);
        $this->assertEquals($userCoupon->id, $validatedCoupon->id);

        // Step 4: User redeems coupon at venue/event
        $redeemedCoupon = $this->couponService->redeemCoupon(
            $userCoupon->unique_code,
            'Main Event Venue',
            [
                'event_name' => 'Summer Festival 2024',
                'scanner_device' => 'iPad-001',
                'staff_member' => 'John Doe'
            ]
        );

        $this->assertEquals(UserCouponStatusEnum::FULLY_USED, $redeemedCoupon->status);
        $this->assertEquals(1, $redeemedCoupon->times_used);

        // Verify usage log was created
        $this->assertDatabaseHas('coupon_usage_logs', [
            'user_coupon_id' => $redeemedCoupon->id,
            'location' => 'Main Event Venue',
        ]);

        // Step 5: Attempt second redemption (should fail)
        $this->expectException(\App\Modules\Coupon\Exceptions\CouponAlreadyUsedException::class);
        $this->couponService->redeemCoupon($userCoupon->unique_code);
    }

    public function test_multi_use_coupon_workflow()
    {
        // Create multi-use coupon
        $couponData = new CouponData(
            organizer_id: $this->organizer->id,
            name: 'Multi-Use Loyalty Coupon',
            description: 'Can be used 3 times',
            code: 'E2E_MULTI',
            type: CouponTypeEnum::MULTI_USE,
            discount_value: 10,
            discount_type: 'percentage',
            max_issuance: 50,
            valid_from: now()->subDay()->format('Y-m-d H:i:s'),
            expires_at: now()->addMonth()->format('Y-m-d H:i:s'),
        );

        $coupon = $this->couponService->upsertCoupon($couponData);

        // Issue with 3 allowed uses
        $issuanceData = new IssueCouponData(
            coupon_id: $coupon->id,
            user_id: $this->user->id,
            times_can_be_used: 3,
            quantity: 1
        );

        $issuedCoupons = $this->couponService->issueCoupon($issuanceData);
        $userCoupon = $issuedCoupons[0];

        // Test each redemption
        $venues = ['Coffee Shop', 'Restaurant', 'Bookstore'];

        for ($use = 1; $use <= 3; $use++) {
            // Validate before each use
            $this->couponService->validateCoupon($userCoupon->unique_code);

            // Redeem
            $userCoupon = $this->couponService->redeemCoupon(
                $userCoupon->unique_code,
                $venues[$use - 1],
                ['use_number' => $use, 'purchase_amount' => 50.00 * $use]
            );

            $this->assertEquals($use, $userCoupon->times_used);

            if ($use < 3) {
                $this->assertEquals(UserCouponStatusEnum::ACTIVE, $userCoupon->status);
            } else {
                $this->assertEquals(UserCouponStatusEnum::FULLY_USED, $userCoupon->status);
            }
        }

        // Verify fourth use fails
        $this->expectException(\App\Modules\Coupon\Exceptions\CouponAlreadyUsedException::class);
        $this->couponService->redeemCoupon($userCoupon->unique_code);
    }

    public function test_multi_user_coupon_distribution_workflow()
    {
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Create multi-use coupon that allows multiple instances per template
        $couponData = new CouponData(
            organizer_id: $this->organizer->id,
            name: 'Event Distribution Coupon',
            description: 'For multiple users',
            code: 'E2E_DISTRIBUTION',
            type: CouponTypeEnum::MULTI_USE,
            discount_value: 2000,
            discount_type: 'fixed',
            max_issuance: 1000,
            valid_from: now()->subDay()->format('Y-m-d H:i:s'),
            expires_at: now()->addWeek()->format('Y-m-d H:i:s'),
        );

        $coupon = $this->couponService->upsertCoupon($couponData);

        // Issue coupons to different users
        $issuance1 = new IssueCouponData(
            coupon_id: $coupon->id,
            user_id: $this->user->id,
            times_can_be_used: 2,
            quantity: 1
        );

        $issuance2 = new IssueCouponData(
            coupon_id: $coupon->id,
            user_id: $user2->id,
            times_can_be_used: 1,
            quantity: 1
        );

        $issuance3 = new IssueCouponData(
            coupon_id: $coupon->id,
            user_id: $user3->id,
            times_can_be_used: 3,
            quantity: 1
        );

        $user1Coupons = $this->couponService->issueCoupon($issuance1);
        $user2Coupons = $this->couponService->issueCoupon($issuance2);
        $user3Coupons = $this->couponService->issueCoupon($issuance3);

        // Verify each user received coupons
        $this->assertCount(1, $user1Coupons);
        $this->assertCount(1, $user2Coupons);
        $this->assertCount(1, $user3Coupons);

        // Use some coupons
        $this->couponService->redeemCoupon($user1Coupons[0]->unique_code, 'Venue A');
        $this->couponService->redeemCoupon($user2Coupons[0]->unique_code, 'Venue B');
        $this->couponService->redeemCoupon($user3Coupons[0]->unique_code, 'Venue C');

        // Verify statistics
        $stats = $this->couponService->getCouponStatistics($coupon->id);
        $this->assertEquals(3, $stats['total_issued']);
        $this->assertEquals(3, $stats['total_redeemed']); // All users have redeemed at least once
        $this->assertEquals(3, $stats['total_redemptions']); // 3 usage logs
        $this->assertEquals(2, $stats['active_coupons']); // user1 and user3 still have uses left
        $this->assertEquals(1, $stats['fully_used_coupons']); // user2's is fully used (1 use only)
    }

    public function test_validate_coupon_with_invalid_code_throws_exception()
    {
        $this->expectException(\App\Modules\Coupon\Exceptions\InvalidCouponException::class);
        $this->expectExceptionMessage('Coupon not found');

        $this->couponService->validateCoupon('INVALID_CODE');
    }

    public function test_issuing_an_expired_coupon_throws_exception()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Coupon has expired');

        $couponData = new CouponData(
            organizer_id: $this->organizer->id,
            name: 'Expired Coupon',
            description: 'This coupon is expired on creation',
            code: 'EXPIRED',
            type: CouponTypeEnum::SINGLE_USE,
            discount_value: 10,
            discount_type: 'percentage',
            max_issuance: 10,
            valid_from: now()->subMonth()->format('Y-m-d H:i:s'),
            expires_at: now()->subDay()->format('Y-m-d H:i:s'),
            id: null
        );
        $coupon = $this->couponService->upsertCoupon($couponData);
        $issuanceData = new IssueCouponData(coupon_id: $coupon->id, user_id: $this->user->id);

        $this->couponService->issueCoupon($issuanceData);
    }

    public function test_validating_a_post_issuance_expired_coupon_throws_exception()
    {
        $this->expectException(\App\Modules\Coupon\Exceptions\CouponExpiredException::class);
        $this->expectExceptionMessage('Coupon has expired');

        // Create and issue a valid coupon
        $couponData = new CouponData(
            organizer_id: $this->organizer->id,
            name: 'Will Expire Coupon',
            description: 'This coupon will expire after issuance',
            code: 'WILL_EXPIRE',
            type: CouponTypeEnum::SINGLE_USE,
            discount_value: 10,
            discount_type: 'percentage',
            max_issuance: 10,
            valid_from: now()->subDay()->format('Y-m-d H:i:s'),
            expires_at: now()->addDay()->format('Y-m-d H:i:s'),
            id: null
        );
        $coupon = $this->couponService->upsertCoupon($couponData);
        $issuanceData = new IssueCouponData(coupon_id: $coupon->id, user_id: $this->user->id);
        $issuedCoupons = $this->couponService->issueCoupon($issuanceData);

        // Make the coupon expire
        $coupon->update(['expires_at' => now()->subMinute()]);

        // Try to validate the now-expired coupon
        $this->couponService->validateCoupon($issuedCoupons[0]->unique_code);
    }
}
