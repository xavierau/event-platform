<?php

namespace Tests\Unit\Domains\Membership\Actions;

use App\Models\User;
use App\Modules\Membership\Actions\CancelMembershipAction;
use App\Modules\Membership\Models\UserMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelMembershipActionTest extends TestCase
{
    use RefreshDatabase;

    private CancelMembershipAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CancelMembershipAction();
    }

    /** @test */
    public function it_can_cancel_an_active_membership()
    {
        // Arrange
        $user = User::factory()->create();
        $membership = UserMembership::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Act
        $this->action->execute($user);

        // Assert
        $this->assertDatabaseHas('user_memberships', [
            'id' => $membership->id,
            'status' => 'cancelled',
        ]);
    }
}
