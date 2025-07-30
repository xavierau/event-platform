<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Services\StripeSubscriptionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class StripeSubscriptionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private StripeSubscriptionSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StripeSubscriptionSyncService();
    }

    public function test_handle_subscription_created_creates_new_membership(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create([
            'metadata' => ['stripe_price_id' => 'price_test123']
        ]);

        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
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

        // Act
        $membership = $this->service->handleSubscriptionCreated($subscription);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $membership);
        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals($level->id, $membership->membership_level_id);
        $this->assertEquals('sub_test123', $membership->stripe_subscription_id);
        $this->assertEquals('cus_test123', $membership->stripe_customer_id);
        $this->assertEquals(MembershipStatus::ACTIVE, $membership->status);
        $this->assertEquals(PaymentMethod::STRIPE, $membership->payment_method);
        $this->assertTrue($membership->auto_renew);
    }

    public function test_handle_subscription_updated_updates_existing_membership(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $level->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => MembershipStatus::ACTIVE,
        ]);

        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'past_due',
            'current_period_start' => now()->subMonth()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => true,
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

        // Act
        $result = $this->service->handleSubscriptionUpdated($subscription);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $result);
        $membership->refresh();
        $this->assertEquals(MembershipStatus::SUSPENDED, $membership->status);
        $this->assertFalse($membership->auto_renew);
        $this->assertEquals('past_due', $membership->subscription_metadata['stripe_status']);
    }

    public function test_handle_subscription_deleted_cancels_membership(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $level->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => MembershipStatus::ACTIVE,
        ]);

        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
            'status' => 'canceled',
            'current_period_start' => now()->subMonth()->timestamp,
            'current_period_end' => now()->addMonth()->timestamp,
            'cancel_at_period_end' => false,
            'ended_at' => now()->addWeek()->timestamp,
        ];

        // Act
        $result = $this->service->handleSubscriptionDeleted($subscription);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $result);
        $membership->refresh();
        $this->assertEquals(MembershipStatus::CANCELLED, $membership->status);
        $this->assertFalse($membership->auto_renew);
    }

    public function test_handle_invoice_payment_succeeded_extends_membership(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $level->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => MembershipStatus::SUSPENDED,
            'expires_at' => now()->addDays(5),
        ]);

        $invoice = (object) [
            'id' => 'in_test123',
            'subscription' => 'sub_test123',
            'period_end' => now()->addMonth()->timestamp,
            'amount_paid' => 2000,
        ];

        // Act
        $result = $this->service->handleInvoicePaymentSucceeded($invoice);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $result);
        $membership->refresh();
        $this->assertEquals(MembershipStatus::ACTIVE, $membership->status);
        $this->assertTrue($membership->expires_at->greaterThan(now()->addDays(25)));
    }

    public function test_handle_invoice_payment_failed_suspends_membership(): void
    {
        // Arrange
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $level->id,
            'stripe_subscription_id' => 'sub_test123',
            'status' => MembershipStatus::ACTIVE,
        ]);

        $invoice = (object) [
            'id' => 'in_test123',
            'subscription' => 'sub_test123',
            'amount_due' => 2000,
            'attempt_count' => 2,
        ];

        // Act
        $result = $this->service->handleInvoicePaymentFailed($invoice);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $result);
        $membership->refresh();
        $this->assertEquals(MembershipStatus::SUSPENDED, $membership->status);
        $this->assertArrayHasKey('last_payment_failure', $membership->subscription_metadata);
        $this->assertEquals('in_test123', $membership->subscription_metadata['last_payment_failure']['invoice_id']);
    }

    public function test_returns_null_when_user_not_found(): void
    {
        // Arrange - This will test the scenario where Stripe customer doesn't match any local user
        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_nonexistent',
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

        // Act - This will fail at the API call but should handle gracefully
        $result = $this->service->handleSubscriptionCreated($subscription);

        // Assert - Should return null for non-existent customer
        $this->assertNull($result);
    }

    public function test_uses_existing_stripe_id_when_available(): void
    {
        // Arrange - User already linked to Stripe
        $user = User::factory()->create(['stripe_id' => 'cus_test123']);
        $level = MembershipLevel::factory()->create([
            'metadata' => ['stripe_price_id' => 'price_test123']
        ]);

        $subscription = (object) [
            'id' => 'sub_test123',
            'customer' => 'cus_test123',
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

        // Act
        $membership = $this->service->handleSubscriptionCreated($subscription);

        // Assert - Should work via existing stripe_id (fast path)
        $this->assertInstanceOf(UserMembership::class, $membership);
        $this->assertEquals($user->id, $membership->user_id);
        $this->assertEquals('sub_test123', $membership->stripe_subscription_id);
    }
}