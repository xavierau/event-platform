<?php

use App\Models\User;
use App\Models\Organizer;
use App\Policies\TeamManagementPolicy;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new TeamManagementPolicy();

    // Create platform admin role
    Role::create(['name' => RoleNameEnum::ADMIN->value]);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN);

    $this->nonMember = User::factory()->create();

    // Create organizer and test users
    $this->organizer = Organizer::factory()->create();

    // Create users with different organizer roles
    $this->owner = User::factory()->create();
    $this->organizer->users()->attach($this->owner->id, [
        'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->manager = User::factory()->create();
    $this->organizer->users()->attach($this->manager->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->staff = User::factory()->create();
    $this->organizer->users()->attach($this->staff->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->viewer = User::factory()->create();
    $this->organizer->users()->attach($this->viewer->id, [
        'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

// ===== INVITE USERS TESTS =====

/** @test */
it('allows platform admin to invite users to any organizer', function () {
    expect($this->policy->inviteUser($this->admin, $this->organizer))->toBeTrue();
});

/** @test */
it('allows organizer owner to invite users', function () {
    expect($this->policy->inviteUser($this->owner, $this->organizer))->toBeTrue();
});

/** @test */
it('allows organizer manager to invite users', function () {
    expect($this->policy->inviteUser($this->manager, $this->organizer))->toBeTrue();
});

/** @test */
it('denies organizer staff from inviting users', function () {
    expect($this->policy->inviteUser($this->staff, $this->organizer))->toBeFalse();
});

/** @test */
it('denies organizer viewer from inviting users', function () {
    expect($this->policy->inviteUser($this->viewer, $this->organizer))->toBeFalse();
});

/** @test */
it('denies non-member from inviting users', function () {
    expect($this->policy->inviteUser($this->nonMember, $this->organizer))->toBeFalse();
});

/** @test */
it('allows user with custom invite permission to invite users', function () {
    $userWithInvitePermission = User::factory()->create();
    $this->organizer->users()->attach($userWithInvitePermission->id, [
        'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
        'permissions' => json_encode([OrganizerPermissionEnum::INVITE_USERS->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->inviteUser($userWithInvitePermission, $this->organizer))->toBeTrue();
});

// ===== REMOVE USERS TESTS =====

/** @test */
it('allows platform admin to remove users from any organizer', function () {
    expect($this->policy->removeUser($this->admin, $this->organizer, $this->staff))->toBeTrue();
});

/** @test */
it('allows organizer owner to remove any member', function () {
    expect($this->policy->removeUser($this->owner, $this->organizer, $this->manager))->toBeTrue();
    expect($this->policy->removeUser($this->owner, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->removeUser($this->owner, $this->organizer, $this->viewer))->toBeTrue();
});

/** @test */
it('allows organizer manager to remove staff and viewers but not owners or other managers', function () {
    expect($this->policy->removeUser($this->manager, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->removeUser($this->manager, $this->organizer, $this->viewer))->toBeTrue();
    expect($this->policy->removeUser($this->manager, $this->organizer, $this->owner))->toBeFalse();

    // Create another manager to test manager-to-manager removal
    $anotherManager = User::factory()->create();
    $this->organizer->users()->attach($anotherManager->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->removeUser($this->manager, $this->organizer, $anotherManager))->toBeFalse();
});

/** @test */
it('denies staff from removing other users', function () {
    expect($this->policy->removeUser($this->staff, $this->organizer, $this->viewer))->toBeFalse();
    expect($this->policy->removeUser($this->staff, $this->organizer, $this->manager))->toBeFalse();
});

/** @test */
it('denies viewer from removing other users', function () {
    expect($this->policy->removeUser($this->viewer, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('denies non-member from removing users', function () {
    expect($this->policy->removeUser($this->nonMember, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('prevents last owner from being removed', function () {
    // Create a separate organizer with only one owner for this test
    $singleOwnerOrganizer = Organizer::factory()->create();
    $singleOwner = User::factory()->create();

    $singleOwnerOrganizer->users()->attach($singleOwner->id, [
        'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->removeUser($this->admin, $singleOwnerOrganizer, $singleOwner))->toBeFalse();
});

// ===== VIEW TEAM MEMBERS TESTS =====

/** @test */
it('allows platform admin to view team members of any organizer', function () {
    expect($this->policy->viewTeamMembers($this->admin, $this->organizer))->toBeTrue();
});

/** @test */
it('allows any active organizer member to view team members', function () {
    expect($this->policy->viewTeamMembers($this->owner, $this->organizer))->toBeTrue();
    expect($this->policy->viewTeamMembers($this->manager, $this->organizer))->toBeTrue();
    expect($this->policy->viewTeamMembers($this->staff, $this->organizer))->toBeTrue();
    expect($this->policy->viewTeamMembers($this->viewer, $this->organizer))->toBeTrue();
});

/** @test */
it('denies non-member from viewing team members', function () {
    expect($this->policy->viewTeamMembers($this->nonMember, $this->organizer))->toBeFalse();
});

// ===== UPDATE USER ROLES TESTS =====

/** @test */
it('allows platform admin to update any user role in any organizer', function () {
    expect($this->policy->updateUserRole($this->admin, $this->organizer, $this->staff))->toBeTrue();
});

/** @test */
it('allows organizer owner to update any member role', function () {
    expect($this->policy->updateUserRole($this->owner, $this->organizer, $this->manager))->toBeTrue();
    expect($this->policy->updateUserRole($this->owner, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->updateUserRole($this->owner, $this->organizer, $this->viewer))->toBeTrue();
});

/** @test */
it('allows organizer manager to update staff and viewer roles but not owners or other managers', function () {
    expect($this->policy->updateUserRole($this->manager, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->updateUserRole($this->manager, $this->organizer, $this->viewer))->toBeTrue();
    expect($this->policy->updateUserRole($this->manager, $this->organizer, $this->owner))->toBeFalse();

    // Create another manager to test manager-to-manager role update
    $anotherManager = User::factory()->create();
    $this->organizer->users()->attach($anotherManager->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->updateUserRole($this->manager, $this->organizer, $anotherManager))->toBeFalse();
});

/** @test */
it('denies staff from updating user roles', function () {
    expect($this->policy->updateUserRole($this->staff, $this->organizer, $this->viewer))->toBeFalse();
});

/** @test */
it('denies viewer from updating user roles', function () {
    expect($this->policy->updateUserRole($this->viewer, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('denies non-member from updating user roles', function () {
    expect($this->policy->updateUserRole($this->nonMember, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('allows user with custom edit team roles permission to update lower-role users', function () {
    $userWithEditRolePermission = User::factory()->create();
    $this->organizer->users()->attach($userWithEditRolePermission->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([OrganizerPermissionEnum::EDIT_TEAM_ROLES->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->updateUserRole($userWithEditRolePermission, $this->organizer, $this->viewer))->toBeTrue();
    expect($this->policy->updateUserRole($userWithEditRolePermission, $this->organizer, $this->manager))->toBeFalse();
});

/** @test */
it('prevents last owner role from being changed', function () {
    // Create a separate organizer with only one owner for this test
    $singleOwnerOrganizer = Organizer::factory()->create();
    $singleOwner = User::factory()->create();

    $singleOwnerOrganizer->users()->attach($singleOwner->id, [
        'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->updateUserRole($this->admin, $singleOwnerOrganizer, $singleOwner))->toBeFalse();
});

// ===== MANAGE PERMISSIONS TESTS =====

/** @test */
it('allows platform admin to manage permissions for any organizer', function () {
    expect($this->policy->managePermissions($this->admin, $this->organizer, $this->staff))->toBeTrue();
});

/** @test */
it('allows organizer owner to manage permissions for any member', function () {
    expect($this->policy->managePermissions($this->owner, $this->organizer, $this->manager))->toBeTrue();
    expect($this->policy->managePermissions($this->owner, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->managePermissions($this->owner, $this->organizer, $this->viewer))->toBeTrue();
});

/** @test */
it('allows organizer manager to manage permissions for staff and viewers but not owners or other managers', function () {
    expect($this->policy->managePermissions($this->manager, $this->organizer, $this->staff))->toBeTrue();
    expect($this->policy->managePermissions($this->manager, $this->organizer, $this->viewer))->toBeTrue();
    expect($this->policy->managePermissions($this->manager, $this->organizer, $this->owner))->toBeFalse();

    // Create another manager to test manager-to-manager permission management
    $anotherManager = User::factory()->create();
    $this->organizer->users()->attach($anotherManager->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->managePermissions($this->manager, $this->organizer, $anotherManager))->toBeFalse();
});

/** @test */
it('denies staff from managing permissions', function () {
    expect($this->policy->managePermissions($this->staff, $this->organizer, $this->viewer))->toBeFalse();
});

/** @test */
it('denies viewer from managing permissions', function () {
    expect($this->policy->managePermissions($this->viewer, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('denies non-member from managing permissions', function () {
    expect($this->policy->managePermissions($this->nonMember, $this->organizer, $this->staff))->toBeFalse();
});

/** @test */
it('allows user with edit team roles permission to manage permissions for lower-role users', function () {
    $userWithEditRolePermission = User::factory()->create();
    $this->organizer->users()->attach($userWithEditRolePermission->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([OrganizerPermissionEnum::EDIT_TEAM_ROLES->value]),
        'is_active' => true,
        'joined_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    expect($this->policy->managePermissions($userWithEditRolePermission, $this->organizer, $this->viewer))->toBeTrue();
    expect($this->policy->managePermissions($userWithEditRolePermission, $this->organizer, $this->manager))->toBeFalse();
});
