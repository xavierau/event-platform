<?php

namespace Tests\Unit\Domains\Membership\Actions;

use App\Models\User;
use App\Modules\Membership\Actions\PurchaseMembershipAction;
use App\Modules\Membership\DataTransferObjects\MembershipPurchaseData;
use App\Modules\Membership\Models\MembershipLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new PurchaseMembershipAction();
    }

    /** @test */
    public function it_can_purchase_a_membership()
    {
        // Arrange
        $user = User::factory()->create();
        $membershipLevel = MembershipLevel::factory()->create(['price' => 1000, 'duration_months' => 1]);

        $data = MembershipPurchaseData::from([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'payment_method' => 'stripe',
        ]);

        // Act
        $this->action->execute($user, $data);

        // Assert
        $this->assertDatabaseHas('user_memberships', [
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
        ]);
    }

    // TODO: Add more tests:
    // - Test invalid membership level
}
