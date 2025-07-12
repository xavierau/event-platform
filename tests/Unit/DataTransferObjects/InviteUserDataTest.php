<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\Organizer\InviteUserData;
use App\Enums\OrganizerRoleEnum;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteUserDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_invite_user_data_with_minimum_required_fields()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        $data = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'newuser@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
        ]);

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals('newuser@example.com', $data->email);
        $this->assertEquals('staff', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertNull($data->custom_permissions);
        $this->assertNull($data->invitation_message);
        $this->assertNull($data->existing_user_id);
        $this->assertNull($data->expires_at); // Should be null when not provided
    }

    public function test_can_create_invite_user_data_with_all_fields()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();
        $existingUser = User::factory()->create();

        $expiresAt = now()->addDays(7);
        $customPermissions = ['create_events', 'edit_events'];
        $message = 'Welcome to our team!';

        $data = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => $existingUser->email,
            'role_in_organizer' => 'manager',
            'invited_by' => $invitedBy->id,
            'custom_permissions' => $customPermissions,
            'invitation_message' => $message,
            'existing_user_id' => $existingUser->id,
            'expires_at' => $expiresAt,
        ]);

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals($existingUser->email, $data->email);
        $this->assertEquals('manager', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertEquals($customPermissions, $data->custom_permissions);
        $this->assertEquals($message, $data->invitation_message);
        $this->assertEquals($existingUser->id, $data->existing_user_id);
        $this->assertEquals($expiresAt, $data->expires_at);
    }

    public function test_can_invite_new_user_by_email()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        $data = InviteUserData::forNewUser(
            organizerId: $organizer->id,
            email: 'newuser@example.com',
            role: 'staff',
            invitedBy: $invitedBy->id,
            customPermissions: ['view_events'],
            message: 'Join our team!'
        );

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals('newuser@example.com', $data->email);
        $this->assertEquals('staff', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertEquals(['view_events'], $data->custom_permissions);
        $this->assertEquals('Join our team!', $data->invitation_message);
        $this->assertNull($data->existing_user_id);
        $this->assertNotNull($data->expires_at);
    }

    public function test_can_invite_existing_user()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();
        $existingUser = User::factory()->create();

        $data = InviteUserData::forExistingUser(
            organizerId: $organizer->id,
            userId: $existingUser->id,
            role: 'manager',
            invitedBy: $invitedBy->id,
            customPermissions: ['manage_team'],
            message: 'Welcome back!'
        );

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals($existingUser->email, $data->email);
        $this->assertEquals('manager', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertEquals(['manage_team'], $data->custom_permissions);
        $this->assertEquals('Welcome back!', $data->invitation_message);
        $this->assertEquals($existingUser->id, $data->existing_user_id);
        $this->assertNotNull($data->expires_at);
    }

    public function test_get_role_enum_returns_correct_enum()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        $data = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'owner',
            'invited_by' => $invitedBy->id,
        ]);

        $role = $data->getRoleEnum();
        $this->assertInstanceOf(OrganizerRoleEnum::class, $role);
        $this->assertEquals(OrganizerRoleEnum::OWNER, $role);
    }

    public function test_is_for_existing_user_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();
        $existingUser = User::factory()->create();

        // For existing user
        $existingUserData = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => $existingUser->email,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'existing_user_id' => $existingUser->id,
        ]);
        $this->assertTrue($existingUserData->isForExistingUser());

        // For new user
        $newUserData = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'newuser@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
        ]);
        $this->assertFalse($newUserData->isForExistingUser());
    }

    public function test_is_expired_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        // Not expired
        $notExpiredData = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->addDays(1),
        ]);
        $this->assertFalse($notExpiredData->isExpired());

        // Expired
        $expiredData = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'expires_at' => now()->subDays(1),
        ]);
        $this->assertTrue($expiredData->isExpired());
    }

    public function test_has_custom_permissions_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        // With custom permissions
        $withPermissions = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'custom_permissions' => ['view_events'],
        ]);
        $this->assertTrue($withPermissions->hasCustomPermissions());

        // Without custom permissions
        $withoutPermissions = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
        ]);
        $this->assertFalse($withoutPermissions->hasCustomPermissions());
    }

    public function test_has_invitation_message_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();

        // With message
        $withMessage = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'invitation_message' => 'Welcome!',
        ]);
        $this->assertTrue($withMessage->hasInvitationMessage());

        // Without message
        $withoutMessage = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => 'user@example.com',
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
        ]);
        $this->assertFalse($withoutMessage->hasInvitationMessage());
    }

    public function test_provides_validation_rules()
    {
        $rules = InviteUserData::rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('organizer_id', $rules);
        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('role_in_organizer', $rules);
        $this->assertArrayHasKey('invited_by', $rules);

        // Check that required fields are marked as required
        $this->assertContains('required', $rules['organizer_id']);
        $this->assertContains('required', $rules['email']);
        $this->assertContains('required', $rules['role_in_organizer']);
        $this->assertContains('required', $rules['invited_by']);

        // Check that nullable fields are marked as nullable
        $this->assertContains('nullable', $rules['custom_permissions']);
        $this->assertContains('nullable', $rules['invitation_message']);
        $this->assertContains('nullable', $rules['existing_user_id']);

        // Check validation types
        $this->assertContains('exists:organizers,id', $rules['organizer_id']);
        $this->assertContains('email', $rules['email']);
        $this->assertContains('in:owner,manager,staff,viewer', $rules['role_in_organizer']);
        $this->assertContains('exists:users,id', $rules['invited_by']);
    }

    public function test_to_organizer_user_data_transformation()
    {
        $organizer = Organizer::factory()->create();
        $invitedBy = User::factory()->create();
        $user = User::factory()->create();

        $inviteData = InviteUserData::from([
            'organizer_id' => $organizer->id,
            'email' => $user->email,
            'role_in_organizer' => 'manager',
            'invited_by' => $invitedBy->id,
            'custom_permissions' => ['edit_events'],
            'existing_user_id' => $user->id,
        ]);

        $organizerUserData = $inviteData->toOrganizerUserData($user->id);

        $this->assertEquals($organizer->id, $organizerUserData->organizer_id);
        $this->assertEquals($user->id, $organizerUserData->user_id);
        $this->assertEquals('manager', $organizerUserData->role_in_organizer);
        $this->assertEquals(['edit_events'], $organizerUserData->permissions);
        $this->assertEquals($invitedBy->id, $organizerUserData->invited_by);
        $this->assertNotNull($organizerUserData->joined_at);
        $this->assertNull($organizerUserData->invitation_accepted_at);
        $this->assertTrue($organizerUserData->is_active);
    }
}
