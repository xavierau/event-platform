<?php

namespace Tests\Unit\DataTransferObjects;

use App\DataTransferObjects\Organizer\OrganizerUserData;
use App\Enums\OrganizerRoleEnum;
use App\Models\Organizer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizerUserDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_organizer_user_data_with_minimum_required_fields()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        $data = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals($user->id, $data->user_id);
        $this->assertEquals('staff', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertNull($data->permissions);
        $this->assertTrue($data->is_active);
        $this->assertNull($data->joined_at);
        $this->assertNull($data->invitation_accepted_at);
    }

    public function test_can_create_organizer_user_data_with_all_fields()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        $joinedAt = now();
        $acceptedAt = now()->addHour();
        $permissions = ['create_events', 'edit_events'];

        $data = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'manager',
            'permissions' => $permissions,
            'joined_at' => $joinedAt,
            'is_active' => false,
            'invited_by' => $invitedBy->id,
            'invitation_accepted_at' => $acceptedAt,
        ]);

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals($user->id, $data->user_id);
        $this->assertEquals('manager', $data->role_in_organizer);
        $this->assertEquals($permissions, $data->permissions);
        $this->assertEquals($joinedAt, $data->joined_at);
        $this->assertFalse($data->is_active);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertEquals($acceptedAt, $data->invitation_accepted_at);
    }

    public function test_provides_validation_rules()
    {
        $rules = OrganizerUserData::rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('organizer_id', $rules);
        $this->assertArrayHasKey('user_id', $rules);
        $this->assertArrayHasKey('role_in_organizer', $rules);
        $this->assertArrayHasKey('invited_by', $rules);

        // Check that required fields are marked as required
        $this->assertContains('required', $rules['organizer_id']);
        $this->assertContains('required', $rules['user_id']);
        $this->assertContains('required', $rules['role_in_organizer']);
        $this->assertContains('required', $rules['invited_by']);

        // Check that nullable fields are marked as nullable
        $this->assertContains('nullable', $rules['permissions']);
        $this->assertContains('nullable', $rules['joined_at']);
        $this->assertContains('nullable', $rules['invitation_accepted_at']);

        // Check validation types
        $this->assertContains('exists:organizers,id', $rules['organizer_id']);
        $this->assertContains('exists:users,id', $rules['user_id']);
        $this->assertContains('in:owner,manager,staff,viewer', $rules['role_in_organizer']);
        $this->assertContains('array', $rules['permissions']);
    }

    public function test_get_role_enum_returns_correct_enum()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        $data = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'owner',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);

        $role = $data->getRoleEnum();
        $this->assertInstanceOf(OrganizerRoleEnum::class, $role);
        $this->assertEquals(OrganizerRoleEnum::OWNER, $role);
    }

    public function test_can_manage_users_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        // Owner can manage users
        $ownerData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'owner',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertTrue($ownerData->canManageUsers());

        // Manager can manage users
        $managerData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'manager',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertTrue($managerData->canManageUsers());

        // Staff cannot manage users
        $staffData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertFalse($staffData->canManageUsers());

        // Viewer cannot manage users
        $viewerData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'viewer',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertFalse($viewerData->canManageUsers());
    }

    public function test_can_manage_organizer_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        // Only owner can manage organizer
        $ownerData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'owner',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertTrue($ownerData->canManageOrganizer());

        // Manager cannot manage organizer
        $managerData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'manager',
            'invited_by' => $invitedBy->id,
            'is_active' => true,
        ]);
        $this->assertFalse($managerData->canManageOrganizer());
    }

    public function test_is_pending_invitation_helper_method()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        // Invitation sent but not accepted
        $pendingData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'joined_at' => now(),
            'invitation_accepted_at' => null,
            'is_active' => true,
        ]);
        $this->assertTrue($pendingData->isPendingInvitation());

        // Invitation accepted
        $acceptedData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'joined_at' => now(),
            'invitation_accepted_at' => now(),
            'is_active' => true,
        ]);
        $this->assertFalse($acceptedData->isPendingInvitation());
    }

    public function test_for_invitation_static_method()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        $data = OrganizerUserData::forInvitation(
            organizerId: $organizer->id,
            userId: $user->id,
            role: 'manager',
            invitedBy: $invitedBy->id,
            customPermissions: ['create_events']
        );

        $this->assertEquals($organizer->id, $data->organizer_id);
        $this->assertEquals($user->id, $data->user_id);
        $this->assertEquals('manager', $data->role_in_organizer);
        $this->assertEquals($invitedBy->id, $data->invited_by);
        $this->assertEquals(['create_events'], $data->permissions);
        $this->assertNotNull($data->joined_at);
        $this->assertNull($data->invitation_accepted_at);
        $this->assertTrue($data->is_active);
    }

    public function test_for_acceptance_method()
    {
        $organizer = Organizer::factory()->create();
        $user = User::factory()->create();
        $invitedBy = User::factory()->create();

        $originalData = OrganizerUserData::from([
            'organizer_id' => $organizer->id,
            'user_id' => $user->id,
            'role_in_organizer' => 'staff',
            'invited_by' => $invitedBy->id,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $acceptedData = $originalData->forAcceptance();

        $this->assertNotNull($acceptedData->invitation_accepted_at);
        $this->assertEquals($originalData->organizer_id, $acceptedData->organizer_id);
        $this->assertEquals($originalData->user_id, $acceptedData->user_id);
        $this->assertEquals($originalData->role_in_organizer, $acceptedData->role_in_organizer);
    }
}
