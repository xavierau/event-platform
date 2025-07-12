<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Modules\Membership\Enums\PaymentMethod;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Modules\Wallet\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->walletService = $this->app->make(WalletService::class);
    }

    #[Test]
    public function it_can_get_membership_levels()
    {
        // Arrange
        MembershipLevel::factory()->count(2)->create(['is_active' => true]);
        MembershipLevel::factory()->create(['is_active' => false]);

        // Act
        $response = $this->getJson('/api/v1/memberships/levels');

        // Assert
        $response->assertOk();
        $response->assertJsonCount(2);
    }

    #[Test]
    public function it_can_get_my_membership()
    {
        // Arrange
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $this->user->id,
            'membership_level_id' => $level->id,
        ]);

        // Act
        $response = $this->actingAs($this->user)->getJson('/api/v1/memberships/my-membership');

        // Assert
        $response->assertOk();
        $response->assertJson($membership->toArray());
    }

    #[Test]
    public function it_returns_unauthorized_if_user_is_not_authenticated_when_getting_membership()
    {
        // Act
        $response = $this->getJson('/api/v1/memberships/my-membership');

        // Assert
        $response->assertUnauthorized();
    }

    #[Test]
    public function it_can_purchase_a_membership()
    {
        // Arrange
        $level = MembershipLevel::factory()->create([
            'price' => 1000,
            'points_cost' => 500,
        ]);
        $this->walletService->addPoints($this->user, 500, 'Initial balance');
        $this->user->refresh();

        $data = [
            'membership_level_id' => $level->id,
            'payment_method' => PaymentMethod::POINTS->value,
        ];

        // Act
        $response = $this->actingAs($this->user)->postJson('/api/v1/memberships/purchase', $data);

        // Assert
        $response->assertOk();
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $this->user->id,
            'membership_level_id' => $level->id,
        ]);
    }

    #[Test]
    public function it_can_renew_a_membership()
    {
        // Arrange
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $this->user->id,
            'membership_level_id' => $level->id,
            'expires_at' => now()->addMonth(),
        ]);
        $originalExpiry = $membership->expires_at;

        // Act
        $response = $this->actingAs($this->user)->postJson('/api/v1/memberships/renew');

        // Assert
        $response->assertOk();
        $membership->refresh();
        $this->assertTrue($membership->expires_at->isAfter($originalExpiry));
    }

    #[Test]
    public function it_can_cancel_a_membership()
    {
        // Arrange
        $level = MembershipLevel::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $this->user->id,
            'membership_level_id' => $level->id,
            'status' => 'active',
        ]);

        // Act
        $response = $this->actingAs($this->user)->deleteJson('/api/v1/memberships/cancel');

        // Assert
        $response->assertOk();
        $membership->refresh();
        $this->assertEquals('cancelled', $membership->status->value);
    }
}
