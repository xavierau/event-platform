<?php

use App\Models\User;
use App\Models\Venue;
use App\Models\Organizer;
use App\Policies\VenuePolicy;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use App\Enums\OrganizerPermissionEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new VenuePolicy();

    // Create required roles
    Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => RoleNameEnum::ORGANIZER->value, 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN);

    $this->nonMember = User::factory()->create();

    // Create organizers and test venues
    $this->organizer = Organizer::factory()->create();
    $this->publicVenue = Venue::factory()->public()->create();
    $this->organizerVenue = Venue::factory()->forOrganizer($this->organizer)->create();

    // Create users with organizer memberships
    $this->organizerOwner = User::factory()->create();
    $this->organizer->users()->attach($this->organizerOwner->id, [
        'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this->organizerManager = User::factory()->create();
    $this->organizer->users()->attach($this->organizerManager->id, [
        'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this->organizerStaff = User::factory()->create();
    $this->organizer->users()->attach($this->organizerStaff->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    $this->organizerViewer = User::factory()->create();
    $this->organizer->users()->attach($this->organizerViewer->id, [
        'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // Create user from different organizer
    $this->otherOrganizer = Organizer::factory()->create();
    $this->userFromDifferentOrganizer = User::factory()->create();
    $this->otherOrganizer->users()->attach($this->userFromDifferentOrganizer->id, [
        'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
        'permissions' => json_encode([]),
        'is_active' => true,
        'joined_at' => now(),
    ]);

    // Create inactive member
    $this->inactiveMember = User::factory()->create();
    $this->organizer->users()->attach($this->inactiveMember->id, [
        'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
        'permissions' => json_encode([]),
        'is_active' => false,
        'joined_at' => now(),
    ]);
});

describe('viewAny', function () {
    test('admin can view any venues', function () {
        expect($this->policy->viewAny($this->admin))->toBeTrue();
    });

    test('organizer members can view venues', function () {
        expect($this->policy->viewAny($this->organizerOwner))->toBeTrue();
        expect($this->policy->viewAny($this->organizerManager))->toBeTrue();
        expect($this->policy->viewAny($this->organizerStaff))->toBeTrue();
        expect($this->policy->viewAny($this->organizerViewer))->toBeTrue();
    });

    test('non-organizer members cannot view venues', function () {
        expect($this->policy->viewAny($this->nonMember))->toBeFalse();
    });

    test('inactive organizer members cannot view venues', function () {
        expect($this->policy->viewAny($this->inactiveMember))->toBeFalse();
    });
});

describe('view', function () {
    test('admin can view any venue', function () {
        expect($this->policy->view($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members can view public venues', function () {
        expect($this->policy->view($this->organizerOwner, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerManager, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerStaff, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerViewer, $this->publicVenue))->toBeTrue();
    });

    test('organizer members can view their own organizer venues', function () {
        expect($this->policy->view($this->organizerOwner, $this->organizerVenue))->toBeTrue();
        expect($this->policy->view($this->organizerManager, $this->organizerVenue))->toBeTrue();
        expect($this->policy->view($this->organizerStaff, $this->organizerVenue))->toBeTrue();
        expect($this->policy->view($this->organizerViewer, $this->organizerVenue))->toBeTrue();
    });

    test('users from different organizer cannot view organizer-specific venues', function () {
        expect($this->policy->view($this->userFromDifferentOrganizer, $this->organizerVenue))->toBeFalse();
    });

    test('non-organizer members cannot view any venues', function () {
        expect($this->policy->view($this->nonMember, $this->publicVenue))->toBeFalse();
        expect($this->policy->view($this->nonMember, $this->organizerVenue))->toBeFalse();
    });

    test('inactive organizer members cannot view venues', function () {
        expect($this->policy->view($this->inactiveMember, $this->publicVenue))->toBeFalse();
        expect($this->policy->view($this->inactiveMember, $this->organizerVenue))->toBeFalse();
    });
});

describe('create', function () {
    test('only admin can create venues', function () {
        expect($this->policy->create($this->admin))->toBeTrue();
    });

    test('organizer members cannot create venues', function () {
        expect($this->policy->create($this->organizerOwner))->toBeFalse();
        expect($this->policy->create($this->organizerManager))->toBeFalse();
        expect($this->policy->create($this->organizerStaff))->toBeFalse();
        expect($this->policy->create($this->organizerViewer))->toBeFalse();
    });

    test('non-organizer members cannot create venues', function () {
        expect($this->policy->create($this->nonMember))->toBeFalse();
    });
});

describe('update', function () {
    test('admin can update any venue', function () {
        expect($this->policy->update($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->update($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer owners and managers can update their organizer venues', function () {
        expect($this->policy->update($this->organizerOwner, $this->organizerVenue))->toBeTrue();
        expect($this->policy->update($this->organizerManager, $this->organizerVenue))->toBeTrue();
    });

    test('organizer staff and viewers cannot update organizer venues', function () {
        expect($this->policy->update($this->organizerStaff, $this->organizerVenue))->toBeFalse();
        expect($this->policy->update($this->organizerViewer, $this->organizerVenue))->toBeFalse();
    });

    test('organizer members cannot update public venues', function () {
        expect($this->policy->update($this->organizerOwner, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerManager, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerStaff, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerViewer, $this->publicVenue))->toBeFalse();
    });

    test('users from different organizer cannot update organizer venues', function () {
        expect($this->policy->update($this->userFromDifferentOrganizer, $this->organizerVenue))->toBeFalse();
    });

    test('non-organizer members cannot update any venues', function () {
        expect($this->policy->update($this->nonMember, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->nonMember, $this->organizerVenue))->toBeFalse();
    });
});

describe('delete', function () {
    test('only admin can delete venues', function () {
        expect($this->policy->delete($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->delete($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members cannot delete venues', function () {
        expect($this->policy->delete($this->organizerOwner, $this->organizerVenue))->toBeFalse();
        expect($this->policy->delete($this->organizerManager, $this->organizerVenue))->toBeFalse();
        expect($this->policy->delete($this->organizerStaff, $this->organizerVenue))->toBeFalse();
        expect($this->policy->delete($this->organizerViewer, $this->organizerVenue))->toBeFalse();
    });
});

describe('restore', function () {
    test('only admin can restore venues', function () {
        expect($this->policy->restore($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->restore($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members cannot restore venues', function () {
        expect($this->policy->restore($this->organizerOwner, $this->organizerVenue))->toBeFalse();
        expect($this->policy->restore($this->organizerManager, $this->organizerVenue))->toBeFalse();
    });
});

describe('forceDelete', function () {
    test('only admin can force delete venues', function () {
        expect($this->policy->forceDelete($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->forceDelete($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members cannot force delete venues', function () {
        expect($this->policy->forceDelete($this->organizerOwner, $this->organizerVenue))->toBeFalse();
        expect($this->policy->forceDelete($this->organizerManager, $this->organizerVenue))->toBeFalse();
    });
});

describe('use', function () {
    test('admin can use any venue', function () {
        expect($this->policy->use($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members can use public venues', function () {
        expect($this->policy->use($this->organizerOwner, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerManager, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerStaff, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerViewer, $this->publicVenue))->toBeTrue();
    });

    test('organizer members can use their own organizer venues', function () {
        expect($this->policy->use($this->organizerOwner, $this->organizerVenue))->toBeTrue();
        expect($this->policy->use($this->organizerManager, $this->organizerVenue))->toBeTrue();
        expect($this->policy->use($this->organizerStaff, $this->organizerVenue))->toBeTrue();
        expect($this->policy->use($this->organizerViewer, $this->organizerVenue))->toBeTrue();
    });

    test('users from different organizer cannot use organizer-specific venues', function () {
        expect($this->policy->use($this->userFromDifferentOrganizer, $this->organizerVenue))->toBeFalse();
    });

    test('non-organizer members cannot use any venues', function () {
        expect($this->policy->use($this->nonMember, $this->publicVenue))->toBeFalse();
        expect($this->policy->use($this->nonMember, $this->organizerVenue))->toBeFalse();
    });
});

describe('venue assignment methods', function () {
    test('only admin can assign venues', function () {
        expect($this->policy->assign($this->admin, $this->publicVenue))->toBeTrue();
    });

    test('organizer members cannot assign venues', function () {
        expect($this->policy->assign($this->organizerOwner, $this->publicVenue))->toBeFalse();
        expect($this->policy->assign($this->organizerManager, $this->publicVenue))->toBeFalse();
    });

    test('only admin can unassign venues', function () {
        expect($this->policy->unassign($this->admin, $this->organizerVenue))->toBeTrue();
    });

    test('organizer members cannot unassign venues', function () {
        expect($this->policy->unassign($this->organizerOwner, $this->organizerVenue))->toBeFalse();
        expect($this->policy->unassign($this->organizerManager, $this->organizerVenue))->toBeFalse();
    });

    test('only admin can manage venue assignments', function () {
        expect($this->policy->manageAssignments($this->admin))->toBeTrue();
    });

    test('organizer members cannot manage venue assignments', function () {
        expect($this->policy->manageAssignments($this->organizerOwner))->toBeFalse();
        expect($this->policy->manageAssignments($this->organizerManager))->toBeFalse();
    });
});

describe('custom permissions for venue management', function () {
    test('user with custom edit venues permission can update organizer venues', function () {
        // Create user with only EDIT_VENUES permission (without manager role)
        $userWithEditPermission = User::factory()->create();
        $this->organizer->users()->attach($userWithEditPermission->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::EDIT_VENUES->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        expect($this->policy->update($userWithEditPermission, $this->organizerVenue))->toBeTrue();
    });

    test('user with custom view venues permission can view organizer venues', function () {
        // Create user with only VIEW_VENUES permission (minimal access)
        $userWithViewPermission = User::factory()->create();
        $this->organizer->users()->attach($userWithViewPermission->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_VENUES->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        expect($this->policy->view($userWithViewPermission, $this->organizerVenue))->toBeTrue();
        expect($this->policy->use($userWithViewPermission, $this->organizerVenue))->toBeTrue();
    });

    test('user without venue permissions cannot perform venue operations', function () {
        // Create user with no venue-related permissions
        $userWithoutPermissions = User::factory()->create();
        $this->organizer->users()->attach($userWithoutPermissions->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]), // Only event permission
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Should still be able to view/use because they're a member (fallback to role-based)
        expect($this->policy->view($userWithoutPermissions, $this->organizerVenue))->toBeTrue();
        expect($this->policy->use($userWithoutPermissions, $this->organizerVenue))->toBeTrue();
        // But cannot update without permission
        expect($this->policy->update($userWithoutPermissions, $this->organizerVenue))->toBeFalse();
    });
});
