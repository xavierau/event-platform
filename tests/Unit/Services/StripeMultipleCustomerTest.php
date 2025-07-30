<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Services\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeMultipleCustomerTest extends TestCase
{
    use RefreshDatabase;

    private StripeSubscriptionSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripeSubscriptionSyncService();
    }

    public function test_user_can_have_multiple_stripe_customer_ids(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'stripe_id' => 'cus_primary123'
        ]);

        // Act - Add additional customer ID
        $user->addStripeCustomerId('cus_secondary456');

        // Assert
        $this->assertTrue($user->hasStripeCustomerId('cus_primary123'));
        $this->assertTrue($user->hasStripeCustomerId('cus_secondary456'));
        $this->assertEquals(['cus_primary123', 'cus_secondary456'], $user->getAllStripeCustomerIds());
    }

    public function test_user_scope_finds_user_by_any_customer_id(): void
    {
        // Arrange
        $user = User::factory()->create([
            'stripe_id' => 'cus_primary123',
            'stripe_customer_ids' => ['cus_secondary456', 'cus_tertiary789']
        ]);

        // Act & Assert - Should find user by primary ID
        $foundUser = User::withStripeCustomerId('cus_primary123')->first();
        $this->assertEquals($user->id, $foundUser->id);

        // Should find user by secondary ID
        $foundUser = User::withStripeCustomerId('cus_secondary456')->first();
        $this->assertEquals($user->id, $foundUser->id);

        // Should find user by tertiary ID
        $foundUser = User::withStripeCustomerId('cus_tertiary789')->first();
        $this->assertEquals($user->id, $foundUser->id);

        // Should not find user by non-existent ID
        $foundUser = User::withStripeCustomerId('cus_nonexistent')->first();
        $this->assertNull($foundUser);
    }

    public function test_adding_existing_customer_id_is_idempotent(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_existing123']);

        // Act - Try to add the same ID as primary
        $user->addStripeCustomerId('cus_existing123');

        // Assert - Should remain the same
        $this->assertEquals('cus_existing123', $user->stripe_id);
        $this->assertNull($user->stripe_customer_ids);

        // Act - Add a different ID, then try to add it again
        $user->addStripeCustomerId('cus_additional456');
        $user->addStripeCustomerId('cus_additional456');

        // Assert - Should only appear once
        $this->assertEquals(['cus_additional456'], $user->stripe_customer_ids);
    }

    public function test_subscription_sync_works_with_multiple_customer_ids(): void
    {
        // Arrange - User linked to one customer, subscription from another
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'stripe_id' => 'cus_original123',
            'stripe_customer_ids' => ['cus_secondary456']
        ]);

        $level = MembershipLevel::factory()->create([
            'metadata' => ['stripe_price_id' => 'price_test123']
        ]);

        // Mock subscription from secondary customer
        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_secondary456', // Different from primary stripe_id
            'status' => 'active',
            'current_period_start' => now()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'items' => (object) [
                'data' => [
                    (object) [
                        'price' => (object) [
                            'id' => 'price_test123'
                        ]
                    ]
                ]
            ]
        ];

        // Act - Should work even though customer ID is different
        $membership = $this->service->handleSubscriptionCreated($subscription);

        // Assert - Should create membership successfully
        $this->assertNotNull($membership);
        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals('cus_secondary456', $membership->stripe_customer_id);
    }
}