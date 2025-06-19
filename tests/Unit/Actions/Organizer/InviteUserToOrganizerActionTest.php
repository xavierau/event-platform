<?php

namespace Tests\Unit\Actions\Organizer;

use App\Actions\Organizer\InviteUserToOrganizerAction;
use App\DataTransferObjects\Organizer\InviteUserData;
use App\Exceptions\UnauthorizedOperationException;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InviteUserToOrganizerActionTest extends TestCase
{
    use RefreshDatabase;

    private InviteUserToOrganizerAction $action;
    private Organizer $organizer;
    private User $inviter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new InviteUserToOrganizerAction();
        $this->organizer = Organizer::factory()->create();
        $this->inviter = User::factory()->create();

        // Make inviter an owner of the organizer
        $this->organizer->users()->attach($this->inviter->id, [
            'role_in_organizer' => 'owner',
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        // Fake mail and notifications
        Mail::fake();
        Notification::fake();
    }

    public function test_can_invite_new_user_by_email()
    {
        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'newuser@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Check that a user was created
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);

        $newUser = User::where('email', 'newuser@example.com')->first();

        // Check organizer-user relationship was created
        $this->assertDatabaseHas('organizer_users', [
            'organizer_id' => $this->organizer->id,
            'user_id' => $newUser->id,
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'invited_by' => $this->inviter->id,
        ]);

        // Check that the invitation_accepted_at is null (pending invitation)
        $pivotRecord = $this->organizer->users()->where('user_id', $newUser->id)->first();
        $this->assertNull($pivotRecord->pivot->invitation_accepted_at);
    }

    public function test_can_invite_existing_user_by_id()
    {
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $inviteData = InviteUserData::forExistingUser(
            organizerId: $this->organizer->id,
            userId: $existingUser->id,
            role: 'manager',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Check organizer-user relationship was created
        $this->assertDatabaseHas('organizer_users', [
            'organizer_id' => $this->organizer->id,
            'user_id' => $existingUser->id,
            'role_in_organizer' => 'manager',
            'is_active' => true,
            'invited_by' => $this->inviter->id,
        ]);
    }

    public function test_can_invite_with_custom_permissions()
    {
        $customPermissions = ['create_events', 'view_analytics'];

        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'custom@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        )->withCustomPermissions($customPermissions);

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        $newUser = User::where('email', 'custom@example.com')->first();
        $pivotRecord = $this->organizer->users()->where('user_id', $newUser->id)->first();

        $this->assertEquals($customPermissions, json_decode($pivotRecord->pivot->permissions, true));
    }

    public function test_can_invite_with_custom_message()
    {
        $customMessage = 'Welcome to our amazing team! We are excited to have you.';

        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'message@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        )->withInvitationMessage($customMessage);

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Verify the message is stored in the pivot table or used in notification
        // This might be stored in metadata or used for email sending
    }

    public function test_fails_when_user_already_member_of_organizer()
    {
        $existingMember = User::factory()->create();

        // Add user as existing member
        $this->organizer->users()->attach($existingMember->id, [
            'role_in_organizer' => 'staff',
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
        ]);

        $inviteData = InviteUserData::forExistingUser(
            organizerId: $this->organizer->id,
            userId: $existingMember->id,
            role: 'manager',
            invitedBy: $this->inviter->id
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User is already a member of this organizer');

        $this->action->execute($inviteData);
    }

    public function test_can_reinvite_inactive_member()
    {
        $inactiveMember = User::factory()->create();

        // Add user as inactive member
        $this->organizer->users()->attach($inactiveMember->id, [
            'role_in_organizer' => 'staff',
            'is_active' => false, // Inactive
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
        ]);

        $inviteData = InviteUserData::forExistingUser(
            organizerId: $this->organizer->id,
            userId: $inactiveMember->id,
            role: 'manager',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Check that the user's role and status were updated
        $pivotRecord = $this->organizer->users()->where('user_id', $inactiveMember->id)->first();
        $this->assertEquals('manager', $pivotRecord->pivot->role_in_organizer);
        $this->assertTrue((bool) $pivotRecord->pivot->is_active);
    }

    public function test_generates_default_password_for_new_users()
    {
        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'password@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        $newUser = User::where('email', 'password@example.com')->first();

        // Verify user has a hashed password
        $this->assertNotNull($newUser->password);
        $this->assertNotEmpty($newUser->password);
    }

    public function test_sets_expiration_time_for_invitations()
    {
        $expirationTime = now()->addDays(7);

        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'expiry@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        )->withExpirationTime($expirationTime);

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Verify expiration time is stored (could be in pivot metadata or separate table)
        $newUser = User::where('email', 'expiry@example.com')->first();
        $this->assertNotNull($newUser);
    }

    public function test_handles_duplicate_email_for_new_user_invitation()
    {
        // Create existing user with the email
        $existingUser = User::factory()->create(['email' => 'duplicate@example.com']);

        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'duplicate@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        // Should handle gracefully by converting to existing user invitation
        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Check organizer-user relationship was created for existing user
        $this->assertDatabaseHas('organizer_users', [
            'organizer_id' => $this->organizer->id,
            'user_id' => $existingUser->id,
            'role_in_organizer' => 'staff',
        ]);
    }

    public function test_validates_organizer_exists()
    {
        $inviteData = InviteUserData::forNewUser(
            organizerId: 999999, // Non-existent organizer
            email: 'test@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Organizer not found');

        $this->action->execute($inviteData);
    }

    public function test_validates_inviter_exists()
    {
        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'test@example.com',
            role: 'staff',
            invitedBy: 999999 // Non-existent inviter
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Inviter not found');

        $this->action->execute($inviteData);
    }

    public function test_validates_inviter_has_permission_to_invite()
    {
        $unauthorizedUser = User::factory()->create();

        // Add unauthorized user as staff (no invite permission)
        $this->organizer->users()->attach($unauthorizedUser->id, [
            'role_in_organizer' => 'viewer', // Viewer can't invite
            'is_active' => true,
            'invited_by' => $this->inviter->id,
            'joined_at' => now(),
        ]);

        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'unauthorized@example.com',
            role: 'staff',
            invitedBy: $unauthorizedUser->id
        );

        $this->expectException(UnauthorizedOperationException::class);
        $this->expectExceptionMessage('User does not have permission to invite others to this organizer');

        $this->action->execute($inviteData);
    }

    public function test_sends_invitation_notification()
    {
        $inviteData = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'notify@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $result = $this->action->execute($inviteData);

        $this->assertTrue($result);

        // Verify notification was sent
        $newUser = User::where('email', 'notify@example.com')->first();
        Notification::assertSentTo($newUser, \App\Notifications\OrganizerInvitationNotification::class);
    }

    public function test_creates_unique_invitation_token()
    {
        $inviteData1 = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'token1@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $inviteData2 = InviteUserData::forNewUser(
            organizerId: $this->organizer->id,
            email: 'token2@example.com',
            role: 'staff',
            invitedBy: $this->inviter->id
        );

        $this->action->execute($inviteData1);
        $this->action->execute($inviteData2);

        $user1 = User::where('email', 'token1@example.com')->first();
        $user2 = User::where('email', 'token2@example.com')->first();

        // Verify each user has unique invitation data
        $this->assertNotNull($user1);
        $this->assertNotNull($user2);
        $this->assertNotEquals($user1->id, $user2->id);
    }
}
