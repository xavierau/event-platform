<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organizer;
use App\Policies\OrganizerPolicy;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class OrganizerPolicyTest extends TestCase
{
    use RefreshDatabase;

    private OrganizerPolicy $policy;
    private User $admin;
    private User $nonMember;
    private User $owner;
    private User $manager;
    private User $staff;
    private User $viewer;
    private Organizer $organizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new OrganizerPolicy();

        // Create roles
        Role::create(['name' => RoleNameEnum::ADMIN->value]);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN);

        $this->nonMember = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->manager = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->viewer = User::factory()->create();

        // Create organizer
        $this->organizer = Organizer::factory()->create();

        // Attach users to organizer with different roles
        $this->organizer->users()->attach($this->owner->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizer->users()->attach($this->manager->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizer->users()->attach($this->staff->id, [
            'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizer->users()->attach($this->viewer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);
    }

    /** @test */
    public function admin_can_view_any_organizer()
    {
        $this->assertTrue($this->policy->viewAny($this->admin));
    }

    /** @test */
    public function organizer_owner_can_view_any_organizer()
    {
        $this->assertTrue($this->policy->viewAny($this->owner));
    }

    /** @test */
    public function organizer_manager_can_view_any_organizer()
    {
        $this->assertTrue($this->policy->viewAny($this->manager));
    }

    /** @test */
    public function organizer_staff_can_view_any_organizer()
    {
        $this->assertTrue($this->policy->viewAny($this->staff));
    }

    /** @test */
    public function organizer_viewer_can_view_any_organizer()
    {
        $this->assertTrue($this->policy->viewAny($this->viewer));
    }

    /** @test */
    public function non_member_cannot_view_any_organizer()
    {
        $this->assertFalse($this->policy->viewAny($this->nonMember));
    }

    /** @test */
    public function admin_can_view_specific_organizer()
    {
        $this->assertTrue($this->policy->view($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_member_can_view_their_organizer()
    {
        $this->assertTrue($this->policy->view($this->owner, $this->organizer));
        $this->assertTrue($this->policy->view($this->manager, $this->organizer));
        $this->assertTrue($this->policy->view($this->staff, $this->organizer));
        $this->assertTrue($this->policy->view($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_view_specific_organizer()
    {
        $this->assertFalse($this->policy->view($this->nonMember, $this->organizer));
    }

    /** @test */
    public function user_cannot_view_organizer_they_are_not_member_of()
    {
        $otherOrganizer = Organizer::factory()->create();
        $this->assertFalse($this->policy->view($this->owner, $otherOrganizer));
    }

    /** @test */
    public function admin_can_create_organizer()
    {
        $this->assertTrue($this->policy->create($this->admin));
    }

    /** @test */
    public function organizer_owner_can_create_organizer()
    {
        $this->assertTrue($this->policy->create($this->owner));
    }

    /** @test */
    public function organizer_manager_can_create_organizer()
    {
        $this->assertTrue($this->policy->create($this->manager));
    }

    /** @test */
    public function organizer_staff_cannot_create_organizer()
    {
        $this->assertFalse($this->policy->create($this->staff));
    }

    /** @test */
    public function organizer_viewer_cannot_create_organizer()
    {
        $this->assertFalse($this->policy->create($this->viewer));
    }

    /** @test */
    public function non_member_cannot_create_organizer()
    {
        $this->assertFalse($this->policy->create($this->nonMember));
    }

    /** @test */
    public function admin_can_update_any_organizer()
    {
        $this->assertTrue($this->policy->update($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_owner_can_update_their_organizer()
    {
        $this->assertTrue($this->policy->update($this->owner, $this->organizer));
    }

    /** @test */
    public function organizer_manager_can_update_their_organizer()
    {
        $this->assertTrue($this->policy->update($this->manager, $this->organizer));
    }

    /** @test */
    public function organizer_staff_cannot_update_organizer()
    {
        $this->assertFalse($this->policy->update($this->staff, $this->organizer));
    }

    /** @test */
    public function organizer_viewer_cannot_update_organizer()
    {
        $this->assertFalse($this->policy->update($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_update_organizer()
    {
        $this->assertFalse($this->policy->update($this->nonMember, $this->organizer));
    }

    /** @test */
    public function user_cannot_update_organizer_they_are_not_member_of()
    {
        $otherOrganizer = Organizer::factory()->create();
        $this->assertFalse($this->policy->update($this->owner, $otherOrganizer));
    }

    /** @test */
    public function admin_can_delete_any_organizer()
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_owner_can_delete_their_organizer()
    {
        $this->assertTrue($this->policy->delete($this->owner, $this->organizer));
    }

    /** @test */
    public function organizer_manager_cannot_delete_organizer()
    {
        $this->assertFalse($this->policy->delete($this->manager, $this->organizer));
    }

    /** @test */
    public function organizer_staff_cannot_delete_organizer()
    {
        $this->assertFalse($this->policy->delete($this->staff, $this->organizer));
    }

    /** @test */
    public function organizer_viewer_cannot_delete_organizer()
    {
        $this->assertFalse($this->policy->delete($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_delete_organizer()
    {
        $this->assertFalse($this->policy->delete($this->nonMember, $this->organizer));
    }

    /** @test */
    public function user_cannot_delete_organizer_they_are_not_member_of()
    {
        $otherOrganizer = Organizer::factory()->create();
        $this->assertFalse($this->policy->delete($this->owner, $otherOrganizer));
    }

    /** @test */
    public function admin_can_restore_any_organizer()
    {
        $this->assertTrue($this->policy->restore($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_owner_can_restore_their_organizer()
    {
        $this->assertTrue($this->policy->restore($this->owner, $this->organizer));
    }

    /** @test */
    public function organizer_manager_cannot_restore_organizer()
    {
        $this->assertFalse($this->policy->restore($this->manager, $this->organizer));
    }

    /** @test */
    public function organizer_staff_cannot_restore_organizer()
    {
        $this->assertFalse($this->policy->restore($this->staff, $this->organizer));
    }

    /** @test */
    public function organizer_viewer_cannot_restore_organizer()
    {
        $this->assertFalse($this->policy->restore($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_restore_organizer()
    {
        $this->assertFalse($this->policy->restore($this->nonMember, $this->organizer));
    }

    /** @test */
    public function only_admin_can_force_delete_organizer()
    {
        $this->assertTrue($this->policy->forceDelete($this->admin, $this->organizer));
        $this->assertFalse($this->policy->forceDelete($this->owner, $this->organizer));
        $this->assertFalse($this->policy->forceDelete($this->manager, $this->organizer));
        $this->assertFalse($this->policy->forceDelete($this->staff, $this->organizer));
        $this->assertFalse($this->policy->forceDelete($this->viewer, $this->organizer));
        $this->assertFalse($this->policy->forceDelete($this->nonMember, $this->organizer));
    }

    /** @test */
    public function admin_can_manage_team_for_any_organizer()
    {
        $this->assertTrue($this->policy->manageTeam($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_owner_can_manage_team()
    {
        $this->assertTrue($this->policy->manageTeam($this->owner, $this->organizer));
    }

    /** @test */
    public function organizer_manager_can_manage_team()
    {
        $this->assertTrue($this->policy->manageTeam($this->manager, $this->organizer));
    }

    /** @test */
    public function organizer_staff_cannot_manage_team()
    {
        $this->assertFalse($this->policy->manageTeam($this->staff, $this->organizer));
    }

    /** @test */
    public function organizer_viewer_cannot_manage_team()
    {
        $this->assertFalse($this->policy->manageTeam($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_manage_team()
    {
        $this->assertFalse($this->policy->manageTeam($this->nonMember, $this->organizer));
    }

    /** @test */
    public function user_with_custom_team_management_permission_can_manage_team()
    {
        // Give staff user custom team management permission
        $this->organizer->users()->updateExistingPivot($this->staff->id, [
            'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_TEAM->value])
        ]);

        $this->assertTrue($this->policy->manageTeam($this->staff, $this->organizer));
    }

    /** @test */
    public function admin_can_manage_settings_for_any_organizer()
    {
        $this->assertTrue($this->policy->manageSettings($this->admin, $this->organizer));
    }

    /** @test */
    public function organizer_owner_can_manage_settings()
    {
        $this->assertTrue($this->policy->manageSettings($this->owner, $this->organizer));
    }

    /** @test */
    public function organizer_manager_can_manage_settings()
    {
        $this->assertTrue($this->policy->manageSettings($this->manager, $this->organizer));
    }

    /** @test */
    public function organizer_staff_cannot_manage_settings()
    {
        $this->assertFalse($this->policy->manageSettings($this->staff, $this->organizer));
    }

    /** @test */
    public function organizer_viewer_cannot_manage_settings()
    {
        $this->assertFalse($this->policy->manageSettings($this->viewer, $this->organizer));
    }

    /** @test */
    public function non_member_cannot_manage_settings()
    {
        $this->assertFalse($this->policy->manageSettings($this->nonMember, $this->organizer));
    }

    /** @test */
    public function user_with_custom_settings_permission_can_manage_settings()
    {
        // Give staff user custom settings management permission
        $this->organizer->users()->updateExistingPivot($this->staff->id, [
            'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_ORGANIZER_SETTINGS->value])
        ]);

        $this->assertTrue($this->policy->manageSettings($this->staff, $this->organizer));
    }

    /** @test */
    public function inactive_member_cannot_perform_actions()
    {
        // Make owner inactive
        $this->organizer->users()->updateExistingPivot($this->owner->id, [
            'is_active' => false
        ]);

        $this->assertFalse($this->policy->view($this->owner, $this->organizer));
        $this->assertFalse($this->policy->update($this->owner, $this->organizer));
        $this->assertFalse($this->policy->delete($this->owner, $this->organizer));
    }
}
