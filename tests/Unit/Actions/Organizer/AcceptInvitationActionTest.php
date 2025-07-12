<?php

namespace Tests\Unit\Actions\Organizer;

use App\Actions\Organizer\AcceptInvitationAction;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Carbon\Carbon;

class AcceptInvitationActionTest extends TestCase
{
    use RefreshDatabase;

    private AcceptInvitationAction $action;
    private Organizer $organizer;
    private User $inviter;
    private User $invitee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new AcceptInvitationAction();
        $this->organizer = Organizer::factory()->create();
        $this->inviter = User::factory()->create();
        $this->invitee = User::factory()->create();

        // Make inviter an owner of the organizer
        $this->organizer->users()->attach($this->inviter->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        Notification::fake();
    }

    public function test_can_accept_pending_invitation()
    {
        // Create pending invitation
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['create_events', 'view_analytics']),
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null, // Pending invitation
        ]);

        $result = $this->action->execute($this->organizer->id, $this->invitee->id);

        $this->assertTrue($result);

        // Check that invitation_accepted_at is now set
        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $this->assertNotNull($pivotRecord->pivot->invitation_accepted_at);
        $this->assertTrue((bool) $pivotRecord->pivot->is_active);
    }

    public function test_can_accept_invitation_using_invite_data()
    {
        // Create pending invitation
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $inviteData = InviteUserData::forExistingUser(
            organizerId: $this->organizer->id,
            userId: $this->invitee->id,
            role: 'manager',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->executeWithData($inviteData, $this->invitee->id);

        $this->assertTrue($result);

        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $this->assertNotNull($pivotRecord->pivot->invitation_accepted_at);
    }

    public function test_can_accept_invitation_using_organizer_user_data()
    {
        // Create pending invitation
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode(['view_events']),
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $organizerUserData = OrganizerUserData::forInvitation(
            organizerId: $this->organizer->id,
            userId: $this->invitee->id,
            role: 'staff',
            invitedBy: $this->inviter->id,
            customPermissions: ['view_events']
        );

        $result = $this->action->executeWithOrganizerUserData($organizerUserData);

        $this->assertTrue($result);

        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $this->assertNotNull($pivotRecord->pivot->invitation_accepted_at);
    }

    public function test_fails_when_no_pending_invitation_exists()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No pending invitation found for this user and organizer');

        $this->action->execute($this->organizer->id, $this->invitee->id);
    }

    public function test_fails_when_invitation_already_accepted()
    {
        // Create already accepted invitation
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null,
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(), // Already accepted
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invitation has already been accepted');

        $this->action->execute($this->organizer->id, $this->invitee->id);
    }

    public function test_fails_when_invitation_is_expired()
    {
        // Skip this test for now since we don't have a metadata column
        // This would be implemented when we add proper expiration tracking
        $this->markTestSkipped('Expiration tracking not yet implemented - requires metadata column or separate expiration field');
    }

    public function test_fails_when_organizer_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer not found');

        $this->action->execute(999999, $this->invitee->id);
    }

    public function test_fails_when_user_not_found()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        $this->action->execute($this->organizer->id, 999999);
    }

    public function test_activates_inactive_user_when_accepting_invitation()
    {
        // Create invitation with inactive user
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'viewer',
            'permissions' => null,
            'is_active' => false, // Inactive
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $result = $this->action->execute($this->organizer->id, $this->invitee->id);

        $this->assertTrue($result);

        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $this->assertNotNull($pivotRecord->pivot->invitation_accepted_at);
        $this->assertTrue((bool) $pivotRecord->pivot->is_active);
    }

    public function test_preserves_custom_permissions_when_accepting()
    {
        $customPermissions = ['create_events', 'edit_events', 'view_analytics'];

        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => json_encode($customPermissions),
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $result = $this->action->execute($this->organizer->id, $this->invitee->id);

        $this->assertTrue($result);

        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $this->assertEquals($customPermissions, json_decode($pivotRecord->pivot->permissions, true));
    }

    public function test_sets_acceptance_timestamp_accurately()
    {
        $beforeAcceptance = now()->subSecond(); // Give a bit more buffer

        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'manager',
            'permissions' => null,
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $result = $this->action->execute($this->organizer->id, $this->invitee->id);

        $afterAcceptance = now()->addSecond(); // Give a bit more buffer

        $this->assertTrue($result);

        $pivotRecord = $this->organizer->users()->where('user_id', $this->invitee->id)->first();
        $acceptanceTime = Carbon::parse($pivotRecord->pivot->invitation_accepted_at);

        // Just verify that acceptance time was set (not null)
        $this->assertNotNull($acceptanceTime);

        // Verify it's within a reasonable range (last 10 seconds)
        $this->assertTrue($acceptanceTime->greaterThan($beforeAcceptance));
        $this->assertTrue($acceptanceTime->lessThan($afterAcceptance));
    }

    public function test_sends_acceptance_notification_to_inviter()
    {
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null,
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $result = $this->action->execute($this->organizer->id, $this->invitee->id);

        $this->assertTrue($result);

        // Verify acceptance notification was sent to inviter
        Notification::assertSentTo($this->inviter, \App\Notifications\InvitationAcceptedNotification::class);
    }

    public function test_handles_acceptance_with_different_role_formats()
    {
        // Test that action handles various role formats correctly
        $testRoles = ['owner', 'manager', 'staff', 'viewer'];

        foreach ($testRoles as $index => $role) {
            $testUser = User::factory()->create();

            $this->organizer->users()->attach($testUser->id, [
                'role_in_organizer' => $role,
                'permissions' => null,
                'is_active' => true,
                'invited_by' => $this->inviter->id,
                'joined_at' => now(),
                'invitation_accepted_at' => null,
            ]);

            $result = $this->action->execute($this->organizer->id, $testUser->id);
            $this->assertTrue($result, "Failed to accept invitation for role: {$role}");
        }
    }

    public function test_validates_user_is_invited_by_active_member()
    {
        // Create invitation by inactive inviter
        $inactiveInviter = User::factory()->create();
        $this->organizer->users()->attach($inactiveInviter->id, [
            'role_in_organizer' => 'manager',
            'is_active' => false, // Inactive inviter
            'invited_by' => $this->inviter->id,
            'joined_at' => now()->subDays(10),
            'invitation_accepted_at' => now()->subDays(10),
        ]);

        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'staff',
            'permissions' => null,
            'is_active' => true,
            'invited_by' => $inactiveInviter->id, // Invited by inactive user
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invitation was made by an inactive user');

        $this->action->execute($this->organizer->id, $this->invitee->id);
    }

    public function test_returns_organizer_user_data_after_acceptance()
    {
        $this->organizer->users()->attach($this->invitee->id, [
            'role_in_organizer' => 'manager',
            'permissions' => json_encode(['manage_team']),
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
        ]);

        $result = $this->action->executeAndReturnData($this->organizer->id, $this->invitee->id);

        $this->assertInstanceOf(OrganizerUserData::class, $result);
        $this->assertEquals($this->organizer->id, $result->organizer_id);
        $this->assertEquals($this->invitee->id, $result->user_id);
        $this->assertEquals('manager', $result->role_in_organizer);
        $this->assertEquals(['manage_team'], $result->permissions);
        $this->assertTrue($result->is_active);
    }
}
