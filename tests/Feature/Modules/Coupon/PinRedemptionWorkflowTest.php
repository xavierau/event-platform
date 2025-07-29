<?php

namespace Tests\Feature\Modules\Coupon;

use App\Models\User;
use App\Models\Organizer;
use App\Modules\Coupon\Models\Coupon;
use App\Modules\Coupon\Models\UserCoupon;
use App\Modules\Coupon\Enums\RedemptionMethodEnum;
use App\Modules\Coupon\Enums\UserCouponStatusEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinRedemptionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organizer = Organizer::factory()->create();
    }

    /** @test */
    public function complete_pin_redemption_workflow_success()
    {
        // Arrange: Create a PIN-enabled coupon
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
            'name' => 'Test PIN Coupon',
        ]);

        $userCoupon = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'ABC123',
            'times_can_be_used' => 2,
            'times_used' => 0,
        ]);

        // Act & Assert: Step 1 - Validate PIN first
        $response = $this->postJson('/api/v1/coupons/validate-pin', [
            'unique_code' => 'ABC123',
            'merchant_pin' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'PIN validation successful.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user_coupon' => [
                        'id',
                        'unique_code',
                        'status',
                        'times_can_be_used',
                        'times_used',
                    ],
                    'details',
                ],
            ]);

        // Act & Assert: Step 2 - Redeem via PIN
        $response = $this->postJson('/api/v1/coupons/redeem-by-pin', [
            'unique_code' => 'ABC123',
            'merchant_pin' => '123456',
            'location' => 'Test Store',
            'details' => ['note' => 'PIN redemption test'],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Coupon redeemed successfully via PIN',
            ])
            ->assertJsonStructure([
                'data' => [
                    'user_coupon' => [
                        'id',
                        'times_used',
                    ],
                    'redemption_method',
                ],
            ]);

        // Assert: Verify database state
        $userCoupon->refresh();
        $this->assertEquals(1, $userCoupon->times_used);
        $this->assertEquals(UserCouponStatusEnum::ACTIVE, $userCoupon->status);

        // Assert: Verify usage log was created
        $this->assertDatabaseHas('coupon_usage_logs', [
            'user_coupon_id' => $userCoupon->id,
            'user_id' => $this->user->id,
            'location' => 'Test Store',
        ]);
    }

    /** @test */
    public function pin_validation_fails_with_incorrect_pin()
    {
        // Arrange
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
        ]);

        $userCoupon = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'DEF456',
        ]);

        // Act & Assert: Try to validate with wrong PIN
        $response = $this->postJson('/api/v1/coupons/validate-pin', [
            'unique_code' => 'DEF456',
            'merchant_pin' => '654321', // Wrong PIN
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid PIN or coupon',
                'errors' => ['Invalid merchant PIN'],
            ]);
    }

    /** @test */
    public function pin_redemption_fails_with_qr_only_coupon()
    {
        // Arrange: Create QR-only coupon
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value],
            'merchant_pin' => null,
        ]);

        $userCoupon = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'GHI789',
        ]);

        // Act & Assert: Try to redeem QR-only coupon with PIN
        $response = $this->postJson('/api/v1/coupons/redeem-by-pin', [
            'unique_code' => 'GHI789',
            'merchant_pin' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'PIN validation failed',
                'errors' => ['Coupon does not support PIN redemption'],
            ]);
    }

    /** @test */
    public function pin_redemption_fails_with_expired_coupon()
    {
        // Arrange: Create expired coupon
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
        ]);

        $userCoupon = UserCoupon::factory()->expired()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'JKL012',
        ]);

        // Act & Assert: Try to redeem expired coupon
        $response = $this->postJson('/api/v1/coupons/redeem-by-pin', [
            'unique_code' => 'JKL012',
            'merchant_pin' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Coupon validation failed',
            ])
            ->assertJsonFragment([
                'errors' => ['Coupon has expired'],
            ]);
    }

    /** @test */
    public function pin_redemption_fails_with_fully_used_coupon()
    {
        // Arrange: Create fully used coupon
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '123456',
        ]);

        $userCoupon = UserCoupon::factory()->fullyUsed()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'MNO345',
            'times_can_be_used' => 1,
            'times_used' => 1,
        ]);

        // Act & Assert: Try to redeem fully used coupon
        $response = $this->postJson('/api/v1/coupons/redeem-by-pin', [
            'unique_code' => 'MNO345',
            'merchant_pin' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Coupon validation failed',
            ])
            ->assertJsonFragment([
                'errors' => ['Coupon has been fully used'],
            ]);
    }

    /** @test */
    public function pin_validation_requires_correct_request_format()
    {
        // Test missing unique_code
        $response = $this->postJson('/api/v1/coupons/validate-pin', [
            'merchant_pin' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['unique_code']);

        // Test missing merchant_pin
        $response = $this->postJson('/api/v1/coupons/validate-pin', [
            'unique_code' => 'ABC123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_pin']);

        // Test invalid PIN format (not 6 digits)
        $response = $this->postJson('/api/v1/coupons/validate-pin', [
            'unique_code' => 'ABC123',
            'merchant_pin' => '12345', // Too short
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['merchant_pin']);
    }

    /** @test */
    public function mixed_redemption_methods_work_correctly()
    {
        // Arrange: Create coupon with both QR and PIN methods
        $coupon = Coupon::factory()->create([
            'organizer_id' => $this->organizer->id,
            'redemption_methods' => [RedemptionMethodEnum::QR->value, RedemptionMethodEnum::PIN->value],
            'merchant_pin' => '789012',
        ]);

        $userCoupon = UserCoupon::factory()->active()->create([
            'user_id' => $this->user->id,
            'coupon_id' => $coupon->id,
            'unique_code' => 'PQR678',
            'times_can_be_used' => 3,
            'times_used' => 0,
        ]);

        // Act & Assert: First redemption via PIN
        $response = $this->postJson('/api/v1/coupons/redeem-by-pin', [
            'unique_code' => 'PQR678',
            'merchant_pin' => '789012',
            'location' => 'Store A',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Coupon redeemed successfully via PIN']);

        // Verify first usage
        $userCoupon->refresh();
        $this->assertEquals(1, $userCoupon->times_used);

        // Act & Assert: Second redemption via QR (should also work)
        $response = $this->postJson("/api/v1/coupon-scanner/PQR678/redeem", [
            'location' => 'Store B',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Coupon redeemed successfully via QR code.']);

        // Verify second usage
        $userCoupon->refresh();
        $this->assertEquals(2, $userCoupon->times_used);
        $this->assertEquals(UserCouponStatusEnum::ACTIVE, $userCoupon->status);
    }
}