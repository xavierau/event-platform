<?php

namespace Tests\Unit\Domains\Membership\Actions;

use App\Models\User;
use App\Modules\Membership\Actions\RenewMembershipAction;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenewMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private RenewMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RenewMembershipAction();
    }

    /** @test */
    public function it_can_renew_an_active_membership()
    {
        // Arrange
        $user = User::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'expires_at' => now()->addMonth(),
        ]);

        // Act
        $this->action->execute($user);

        // Assert
        $renewedMembership = $user->membership()->first();
        $this->assertTrue($renewedMembership->expires_at->isAfter($membership->expires_at));
    }

    // TODO: Add more tests:
    // - Cannot renew an expired membership
    // - Cannot renew if auto_renew is off (if that becomes a rule)
}
