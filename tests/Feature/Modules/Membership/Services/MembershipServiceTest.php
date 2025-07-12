<?php

namespace Tests\Feature\Modules\Membership\Services;

use App\Models\User;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Enums\MembershipStatus;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Stripe\Checkout\Session;
use Tests\TestCase;

class MembershipServiceTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private MembershipService $membershipService;
    private WalletService $walletService;
    private User $user;
    private MembershipLevel $level;

    protected function setUp(): void
    {
        parent::setUp();
        $this->membershipService = $this->app->make(MembershipService::class);
        $this->walletService = $this->app->make(WalletService::class);
        $this->user = User::factory()->create();
        $this->level = MembershipLevel::factory()->create([
            'price' => 1000,
            'points_cost' => 1000,
            'duration_months' => 1
        ]);
    }

    /** @test */
    public function it_can_purchase_membership_with_points()
    {
        // Arrange
        $this->walletService->addPoints($this->user, 1500, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $this->level->id,
            payment_method: PaymentMethod::POINTS,
        );

        // Act
        $result = $this->membershipService->purchaseMembership($this->user, $data);

        // Assert
        $this->assertInstanceOf(UserMembership::class, $result);
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $this->user->id,
            'membership_level_id' => $this->level->id,
            'status' => MembershipStatus::ACTIVE,
        ]);

        $this->assertEquals(500, $this->walletService->getBalance($this->user)['points_balance']);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->user->wallet->id,
            'transaction_type' => 'spend_points',
            'amount' => $this->level->price,
        ]);
    }

    /** @test */
    public function it_throws_exception_for_insufficient_points()
    {
        // Arrange
        $this->walletService->addPoints($this->user, 500, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $this->level->id,
            payment_method: PaymentMethod::POINTS,
        );

        // Assert
        $this->expectException(\App\Modules\Wallet\Exceptions\InsufficientPointsException::class);

        // Act
        $this->membershipService->purchaseMembership($this->user, $data);
    }

    /** @test */
    public function it_initiates_stripe_purchase_and_returns_checkout_url()
    {
        // Arrange
        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $this->level->id,
            payment_method: PaymentMethod::STRIPE,
        );

        // Mock Stripe API
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'cs_test_123',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/pay/cs_test_123',
            ], 200),
        ]);

        // Act
        $result = $this->membershipService->purchaseMembership($this->user, $data);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['requires_payment']);
        $this->assertStringContainsString('https://checkout.stripe.com', $result['checkout_url']);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'status' => 'pending_payment',
            'payment_gateway' => 'stripe',
        ]);
    }

    // Tests will go here
}
