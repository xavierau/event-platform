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
    Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);

    // Create users with different roles
    $this->admin = User::factory()->create();
    $this->admin->assignRole(RoleNameEnum::ADMIN);

    $this->regularUser = User::factory()->create();
    $this->regularUser->assignRole(RoleNameEnum::USER);

    // Create organizer entities and users with organizer entity memberships
    $this->organizer1 = \App\Models\Organizer::factory()->create();
    $this->organizer2 = \App\Models\Organizer::factory()->create();

    // Create users and associate them with organizer entities
    $this->organizerOwner = User::factory()->create();
    $this->organizerOwner->assignRole(RoleNameEnum::USER);
    $this->organizer1->users()->attach($this->organizerOwner->id, [
        'role_in_organizer' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->organizerManager = User::factory()->create();
    $this->organizerManager->assignRole(RoleNameEnum::USER);
    $this->organizer1->users()->attach($this->organizerManager->id, [
        'role_in_organizer' => 'manager',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->organizerStaff = User::factory()->create();
    $this->organizerStaff->assignRole(RoleNameEnum::USER);
    $this->organizer1->users()->attach($this->organizerStaff->id, [
        'role_in_organizer' => 'staff',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->organizerViewer = User::factory()->create();
    $this->organizerViewer->assignRole(RoleNameEnum::USER);
    $this->organizer1->users()->attach($this->organizerViewer->id, [
        'role_in_organizer' => 'viewer',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->inactiveOrganizerMember = User::factory()->create();
    $this->inactiveOrganizerMember->assignRole(RoleNameEnum::USER);
    $this->organizer1->users()->attach($this->inactiveOrganizerMember->id, [
        'role_in_organizer' => 'staff',
        'joined_at' => now(),
        'is_active' => false, // Inactive member
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->differentOrganizerMember = User::factory()->create();
    $this->differentOrganizerMember->assignRole(RoleNameEnum::USER);
    $this->organizer2->users()->attach($this->differentOrganizerMember->id, [
        'role_in_organizer' => 'owner',
        'joined_at' => now(),
        'is_active' => true,
        'invitation_accepted_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create test venues
    $this->publicVenue = Venue::factory()->create(['organizer_id' => null]); // Public venue
    $this->organizer1Venue = Venue::factory()->create(['organizer_id' => $this->organizer1->id]); // Organizer 1 venue
    $this->organizer2Venue = Venue::factory()->create(['organizer_id' => $this->organizer2->id]); // Organizer 2 venue
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
        expect($this->policy->viewAny($this->regularUser))->toBeFalse();
    });

    test('inactive organizer members cannot view venues', function () {
        expect($this->policy->viewAny($this->inactiveOrganizerMember))->toBeFalse();
    });
});

describe('view', function () {
    test('admin can view any venue', function () {
        expect($this->policy->view($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members can view public venues', function () {
        expect($this->policy->view($this->organizerOwner, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerManager, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerStaff, $this->publicVenue))->toBeTrue();
        expect($this->policy->view($this->organizerViewer, $this->publicVenue))->toBeTrue();
    });

    test('organizer members can view their own organizer venues', function () {
        expect($this->policy->view($this->organizerOwner, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->view($this->organizerManager, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->view($this->organizerStaff, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->view($this->organizerViewer, $this->organizer1Venue))->toBeTrue();
    });

    test('users from different organizer cannot view organizer-specific venues', function () {
        expect($this->policy->view($this->differentOrganizerMember, $this->organizer1Venue))->toBeFalse();
    });

    test('non-organizer members cannot view any venues', function () {
        expect($this->policy->view($this->regularUser, $this->publicVenue))->toBeFalse();
        expect($this->policy->view($this->regularUser, $this->organizer1Venue))->toBeFalse();
    });

    test('inactive organizer members cannot view venues', function () {
        expect($this->policy->view($this->inactiveOrganizerMember, $this->publicVenue))->toBeFalse();
        expect($this->policy->view($this->inactiveOrganizerMember, $this->organizer1Venue))->toBeFalse();
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
        expect($this->policy->create($this->regularUser))->toBeFalse();
    });
});

describe('update', function () {
    test('admin can update any venue', function () {
        expect($this->policy->update($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->update($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer owners and managers can update their organizer venues', function () {
        expect($this->policy->update($this->organizerOwner, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->update($this->organizerManager, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer staff and viewers cannot update organizer venues', function () {
        expect($this->policy->update($this->organizerStaff, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->update($this->organizerViewer, $this->organizer1Venue))->toBeFalse();
    });

    test('organizer members cannot update public venues', function () {
        expect($this->policy->update($this->organizerOwner, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerManager, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerStaff, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->organizerViewer, $this->publicVenue))->toBeFalse();
    });

    test('users from different organizer cannot update organizer venues', function () {
        expect($this->policy->update($this->differentOrganizerMember, $this->organizer1Venue))->toBeFalse();
    });

    test('non-organizer members cannot update any venues', function () {
        expect($this->policy->update($this->regularUser, $this->publicVenue))->toBeFalse();
        expect($this->policy->update($this->regularUser, $this->organizer1Venue))->toBeFalse();
    });
});

describe('delete', function () {
    test('only admin can delete venues', function () {
        expect($this->policy->delete($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->delete($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members cannot delete venues', function () {
        expect($this->policy->delete($this->organizerOwner, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->delete($this->organizerManager, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->delete($this->organizerStaff, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->delete($this->organizerViewer, $this->organizer1Venue))->toBeFalse();
    });
});

describe('restore', function () {
    test('only admin can restore venues', function () {
        expect($this->policy->restore($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->restore($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members cannot restore venues', function () {
        expect($this->policy->restore($this->organizerOwner, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->restore($this->organizerManager, $this->organizer1Venue))->toBeFalse();
    });
});

describe('forceDelete', function () {
    test('only admin can force delete venues', function () {
        expect($this->policy->forceDelete($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->forceDelete($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members cannot force delete venues', function () {
        expect($this->policy->forceDelete($this->organizerOwner, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->forceDelete($this->organizerManager, $this->organizer1Venue))->toBeFalse();
    });
});

describe('use', function () {
    test('admin can use any venue', function () {
        expect($this->policy->use($this->admin, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members can use public venues', function () {
        expect($this->policy->use($this->organizerOwner, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerManager, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerStaff, $this->publicVenue))->toBeTrue();
        expect($this->policy->use($this->organizerViewer, $this->publicVenue))->toBeTrue();
    });

    test('organizer members can use their own organizer venues', function () {
        expect($this->policy->use($this->organizerOwner, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->use($this->organizerManager, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->use($this->organizerStaff, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->use($this->organizerViewer, $this->organizer1Venue))->toBeTrue();
    });

    test('users from different organizer cannot use organizer-specific venues', function () {
        expect($this->policy->use($this->differentOrganizerMember, $this->organizer1Venue))->toBeFalse();
    });

    test('non-organizer members cannot use any venues', function () {
        expect($this->policy->use($this->regularUser, $this->publicVenue))->toBeFalse();
        expect($this->policy->use($this->regularUser, $this->organizer1Venue))->toBeFalse();
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
        expect($this->policy->unassign($this->admin, $this->organizer1Venue))->toBeTrue();
    });

    test('organizer members cannot unassign venues', function () {
        expect($this->policy->unassign($this->organizerOwner, $this->organizer1Venue))->toBeFalse();
        expect($this->policy->unassign($this->organizerManager, $this->organizer1Venue))->toBeFalse();
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
        $this->organizer1->users()->attach($userWithEditPermission->id, [
            'role_in_organizer' => 'viewer',
            'permissions' => json_encode([OrganizerPermissionEnum::EDIT_VENUES->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        expect($this->policy->update($userWithEditPermission, $this->organizer1Venue))->toBeTrue();
    });

    test('user with custom view venues permission can view organizer venues', function () {
        // Create user with only VIEW_VENUES permission (minimal access)
        $userWithViewPermission = User::factory()->create();
        $this->organizer1->users()->attach($userWithViewPermission->id, [
            'role_in_organizer' => 'viewer',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_VENUES->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        expect($this->policy->view($userWithViewPermission, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->use($userWithViewPermission, $this->organizer1Venue))->toBeTrue();
    });

    test('user without venue permissions cannot perform venue operations', function () {
        // Create user with no venue-related permissions
        $userWithoutPermissions = User::factory()->create();
        $this->organizer1->users()->attach($userWithoutPermissions->id, [
            'role_in_organizer' => 'viewer',
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]), // Only event permission
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Should still be able to view/use because they're a member (fallback to role-based)
        expect($this->policy->view($userWithoutPermissions, $this->organizer1Venue))->toBeTrue();
        expect($this->policy->use($userWithoutPermissions, $this->organizer1Venue))->toBeTrue();
        // But cannot update without permission
        expect($this->policy->update($userWithoutPermissions, $this->organizer1Venue))->toBeFalse();
    });
});
