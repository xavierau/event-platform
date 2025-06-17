<?php

namespace Tests\Feature\Modules\Membership;

use App\Models\User;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Exceptions\PaymentMethodNotAllowedException;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Services\MembershipService;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MembershipPricingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private MembershipService $membershipService;
    private WalletService $walletService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Run the new migration
        $this->artisan('migrate');

        $this->membershipService = $this->app->make(MembershipService::class);
        $this->walletService = $this->app->make(WalletService::class);
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_purchase_membership_with_correct_points_cost()
    {
        // Arrange
        $level = MembershipLevel::factory()->create([
            'price' => 1000, // $10.00
            'points_cost' => 500, // 500 points
        ]);
        $this->walletService->addPoints($this->user, 600, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $level->id,
            payment_method: PaymentMethod::POINTS
        );

        // Act
        $this->membershipService->purchaseMembership($this->user, $data);

        // Assert
        $this->assertEquals(100, $this->walletService->getBalance($this->user)['points_balance']);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->user->wallet->id,
            'amount' => 500, // Asserts the points_cost was used, not the price
        ]);
    }

    /** @test */
    public function it_can_purchase_membership_with_correct_kill_points_cost()
    {
        // Arrange
        $level = MembershipLevel::factory()->create([
            'price' => 1000,
            'kill_points_cost' => 250,
        ]);
        $this->walletService->addKillPoints($this->user, 300, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $level->id,
            payment_method: PaymentMethod::KILL_POINTS
        );

        // Act
        $this->membershipService->purchaseMembership($this->user, $data);

        // Assert
        $this->assertEquals(50, $this->walletService->getBalance($this->user)['kill_points_balance']);
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id' => $this->user->wallet->id,
            'amount' => 250,
            'transaction_type' => 'spend_kill_points',
        ]);
    }

    /** @test */
    public function it_throws_exception_if_purchasing_with_points_when_cost_is_null()
    {
        // Assert
        $this->expectException(PaymentMethodNotAllowedException::class);

        // Arrange
        $level = MembershipLevel::factory()->create([
            'price' => 1000,
            'points_cost' => null, // Not purchasable with points
        ]);
        $this->walletService->addPoints($this->user, 1500, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $level->id,
            payment_method: PaymentMethod::POINTS
        );

        // Act
        $this->membershipService->purchaseMembership($this->user, $data);
    }

    /** @test */
    public function it_throws_exception_if_purchasing_with_kill_points_when_cost_is_null()
    {
        // Assert
        $this->expectException(PaymentMethodNotAllowedException::class);

        // Arrange
        $level = MembershipLevel::factory()->create([
            'price' => 1000,
            'kill_points_cost' => null, // Not purchasable with kill points
        ]);
        $this->walletService->addKillPoints($this->user, 500, 'Initial balance');
        $this->user->refresh();

        $data = new MembershipPurchaseData(
            user_id: $this->user->id,
            membership_level_id: $level->id,
            payment_method: PaymentMethod::KILL_POINTS
        );

        // Act
        $this->membershipService->purchaseMembership($this->user, $data);
    }
}
