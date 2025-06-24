<?php

namespace Tests\Unit\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Event;
use App\Models\Organizer;
use App\Policies\EventPolicy;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected EventPolicy $policy;
    protected User $admin;
    protected User $userWithoutOrganizer;
    protected User $organizerOwner;
    protected User $organizerManager;
    protected User $organizerStaff;
    protected User $organizerViewer;
    protected User $userFromDifferentOrganizer;
    protected Organizer $organizer;
    protected Organizer $differentOrganizer;
    protected Event $publishedEvent;
    protected Event $draftEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EventPolicy();

        // Create required roles
        Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => RoleNameEnum::USER->value, 'guard_name' => 'web']);

        // Create admin user
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN);

        // Create regular user without organizer membership
        $this->userWithoutOrganizer = User::factory()->create();

        // Create organizer and users with different roles
        $this->organizer = Organizer::factory()->create();
        $this->differentOrganizer = Organizer::factory()->create();

        // Create users with different organizer roles
        $this->organizerOwner = User::factory()->create();
        $this->organizer->users()->attach($this->organizerOwner->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizerManager = User::factory()->create();
        $this->organizer->users()->attach($this->organizerManager->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizerStaff = User::factory()->create();
        $this->organizer->users()->attach($this->organizerStaff->id, [
            'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->organizerViewer = User::factory()->create();
        $this->organizer->users()->attach($this->organizerViewer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Create user from different organizer
        $this->userFromDifferentOrganizer = User::factory()->create();
        $this->differentOrganizer->users()->attach($this->userFromDifferentOrganizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Create events
        $this->publishedEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_status' => 'published',
        ]);

        $this->draftEvent = Event::factory()->create([
            'organizer_id' => $this->organizer->id,
            'event_status' => 'draft',
        ]);
    }

    /** @test */
    public function admin_can_view_any_events(): void
    {
        $this->assertTrue($this->policy->viewAny($this->admin));
    }

    /** @test */
    public function user_with_organizer_membership_can_view_any_events(): void
    {
        $this->assertTrue($this->policy->viewAny($this->organizerOwner));
        $this->assertTrue($this->policy->viewAny($this->organizerManager));
        $this->assertTrue($this->policy->viewAny($this->organizerStaff));
        $this->assertTrue($this->policy->viewAny($this->organizerViewer));
    }

    /** @test */
    public function user_without_organizer_membership_cannot_view_any_events(): void
    {
        $this->assertFalse($this->policy->viewAny($this->userWithoutOrganizer));
    }

    /** @test */
    public function admin_can_view_any_event(): void
    {
        $this->assertTrue($this->policy->view($this->admin, $this->publishedEvent));
        $this->assertTrue($this->policy->view($this->admin, $this->draftEvent));
    }

    /** @test */
    public function any_authenticated_user_can_view_published_events(): void
    {
        $this->assertTrue($this->policy->view($this->userWithoutOrganizer, $this->publishedEvent));
        $this->assertTrue($this->policy->view($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->view($this->userFromDifferentOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function only_organizer_members_can_view_non_published_events(): void
    {
        // Organizer members can view draft events
        $this->assertTrue($this->policy->view($this->organizerOwner, $this->draftEvent));
        $this->assertTrue($this->policy->view($this->organizerManager, $this->draftEvent));
        $this->assertTrue($this->policy->view($this->organizerStaff, $this->draftEvent));
        $this->assertTrue($this->policy->view($this->organizerViewer, $this->draftEvent));

        // Users without organizer membership cannot view draft events
        $this->assertFalse($this->policy->view($this->userWithoutOrganizer, $this->draftEvent));

        // Users from different organizer cannot view draft events
        $this->assertFalse($this->policy->view($this->userFromDifferentOrganizer, $this->draftEvent));
    }

    /** @test */
    public function admin_can_always_create_events(): void
    {
        $this->assertTrue($this->policy->create($this->admin));
    }

    /** @test */
    public function users_with_event_management_permissions_can_create_events(): void
    {
        $this->assertTrue($this->policy->create($this->organizerOwner));
        $this->assertTrue($this->policy->create($this->organizerManager));
        $this->assertTrue($this->policy->create($this->organizerStaff));
    }

    /** @test */
    public function users_without_event_management_permissions_cannot_create_events(): void
    {
        $this->assertFalse($this->policy->create($this->organizerViewer));
        $this->assertFalse($this->policy->create($this->userWithoutOrganizer));
    }

    /** @test */
    public function admin_can_update_any_event(): void
    {
        $this->assertTrue($this->policy->update($this->admin, $this->publishedEvent));
        $this->assertTrue($this->policy->update($this->admin, $this->draftEvent));
    }

    /** @test */
    public function organizer_members_with_event_management_permissions_can_update_their_events(): void
    {
        $this->assertTrue($this->policy->update($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->update($this->organizerManager, $this->publishedEvent));
        $this->assertTrue($this->policy->update($this->organizerStaff, $this->publishedEvent));
    }

    /** @test */
    public function organizer_viewers_cannot_update_events(): void
    {
        $this->assertFalse($this->policy->update($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function users_from_different_organizer_cannot_update_events(): void
    {
        $this->assertFalse($this->policy->update($this->userFromDifferentOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function users_without_organizer_membership_cannot_update_events(): void
    {
        $this->assertFalse($this->policy->update($this->userWithoutOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function admin_can_delete_any_event(): void
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->publishedEvent));
        $this->assertTrue($this->policy->delete($this->admin, $this->draftEvent));
    }

    /** @test */
    public function organizer_owners_and_managers_can_delete_their_events(): void
    {
        $this->assertTrue($this->policy->delete($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->delete($this->organizerManager, $this->publishedEvent));
    }

    /** @test */
    public function organizer_staff_and_viewers_cannot_delete_events(): void
    {
        $this->assertFalse($this->policy->delete($this->organizerStaff, $this->publishedEvent));
        $this->assertFalse($this->policy->delete($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function users_from_different_organizer_cannot_delete_events(): void
    {
        $this->assertFalse($this->policy->delete($this->userFromDifferentOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function only_admin_can_force_delete_events(): void
    {
        $this->assertTrue($this->policy->forceDelete($this->admin, $this->publishedEvent));
        $this->assertFalse($this->policy->forceDelete($this->organizerOwner, $this->publishedEvent));
        $this->assertFalse($this->policy->forceDelete($this->organizerManager, $this->publishedEvent));
    }

    /** @test */
    public function admin_can_publish_any_event(): void
    {
        $this->assertTrue($this->policy->publish($this->admin, $this->draftEvent));
    }

    /** @test */
    public function organizer_owners_and_managers_can_publish_their_events(): void
    {
        $this->assertTrue($this->policy->publish($this->organizerOwner, $this->draftEvent));
        $this->assertTrue($this->policy->publish($this->organizerManager, $this->draftEvent));
    }

    /** @test */
    public function organizer_staff_and_viewers_cannot_publish_events(): void
    {
        $this->assertFalse($this->policy->publish($this->organizerStaff, $this->draftEvent));
        $this->assertFalse($this->policy->publish($this->organizerViewer, $this->draftEvent));
    }

    /** @test */
    public function admin_can_manage_occurrences_for_any_event(): void
    {
        $this->assertTrue($this->policy->manageOccurrences($this->admin, $this->publishedEvent));
    }

    /** @test */
    public function organizer_members_with_event_management_permissions_can_manage_occurrences(): void
    {
        $this->assertTrue($this->policy->manageOccurrences($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->manageOccurrences($this->organizerManager, $this->publishedEvent));
        $this->assertTrue($this->policy->manageOccurrences($this->organizerStaff, $this->publishedEvent));
    }

    /** @test */
    public function organizer_viewers_cannot_manage_occurrences(): void
    {
        $this->assertFalse($this->policy->manageOccurrences($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function admin_can_manage_bookings_for_any_event(): void
    {
        $this->assertTrue($this->policy->manageBookings($this->admin, $this->publishedEvent));
    }

    /** @test */
    public function any_organizer_member_can_manage_bookings_for_their_events(): void
    {
        $this->assertTrue($this->policy->manageBookings($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->manageBookings($this->organizerManager, $this->publishedEvent));
        $this->assertTrue($this->policy->manageBookings($this->organizerStaff, $this->publishedEvent));
        $this->assertTrue($this->policy->manageBookings($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function users_from_different_organizer_cannot_manage_bookings(): void
    {
        $this->assertFalse($this->policy->manageBookings($this->userFromDifferentOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function admin_can_manage_media_for_any_event(): void
    {
        $this->assertTrue($this->policy->manageMedia($this->admin, $this->publishedEvent));
    }

    /** @test */
    public function organizer_members_with_event_management_permissions_can_manage_media(): void
    {
        $this->assertTrue($this->policy->manageMedia($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->manageMedia($this->organizerManager, $this->publishedEvent));
        $this->assertTrue($this->policy->manageMedia($this->organizerStaff, $this->publishedEvent));
    }

    /** @test */
    public function organizer_viewers_cannot_manage_media(): void
    {
        $this->assertFalse($this->policy->manageMedia($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function admin_can_duplicate_any_event(): void
    {
        $this->assertTrue($this->policy->duplicate($this->admin, $this->publishedEvent));
    }

    /** @test */
    public function organizer_members_with_event_management_permissions_can_duplicate_their_events(): void
    {
        $this->assertTrue($this->policy->duplicate($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->duplicate($this->organizerManager, $this->publishedEvent));
        $this->assertTrue($this->policy->duplicate($this->organizerStaff, $this->publishedEvent));
    }

    /** @test */
    public function organizer_viewers_cannot_duplicate_events(): void
    {
        $this->assertFalse($this->policy->duplicate($this->organizerViewer, $this->publishedEvent));
    }

    /** @test */
    public function only_admin_can_change_event_organizer(): void
    {
        $this->assertTrue($this->policy->changeOrganizer($this->admin, $this->publishedEvent));
        $this->assertFalse($this->policy->changeOrganizer($this->organizerOwner, $this->publishedEvent));
        $this->assertFalse($this->policy->changeOrganizer($this->organizerManager, $this->publishedEvent));
    }

    /** @test */
    public function restore_follows_same_rules_as_delete(): void
    {
        // Admin can restore any event
        $this->assertTrue($this->policy->restore($this->admin, $this->publishedEvent));

        // Organizer owners and managers can restore their events
        $this->assertTrue($this->policy->restore($this->organizerOwner, $this->publishedEvent));
        $this->assertTrue($this->policy->restore($this->organizerManager, $this->publishedEvent));

        // Organizer staff and viewers cannot restore events
        $this->assertFalse($this->policy->restore($this->organizerStaff, $this->publishedEvent));
        $this->assertFalse($this->policy->restore($this->organizerViewer, $this->publishedEvent));

        // Users from different organizer cannot restore events
        $this->assertFalse($this->policy->restore($this->userFromDifferentOrganizer, $this->publishedEvent));
    }

    /** @test */
    public function user_with_custom_delete_permission_can_delete_events(): void
    {
        // Create user with only DELETE_EVENTS permission (without manager role)
        $userWithDeletePermission = User::factory()->create();
        $this->organizer->users()->attach($userWithDeletePermission->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([\App\Enums\OrganizerPermissionEnum::DELETE_EVENTS->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->policy->delete($userWithDeletePermission, $this->publishedEvent));
        $this->assertTrue($this->policy->restore($userWithDeletePermission, $this->publishedEvent));
    }

    /** @test */
    public function user_with_custom_publish_permission_can_publish_events(): void
    {
        // Create user with only PUBLISH_EVENTS permission (without manager role)
        $userWithPublishPermission = User::factory()->create();
        $this->organizer->users()->attach($userWithPublishPermission->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([\App\Enums\OrganizerPermissionEnum::PUBLISH_EVENTS->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->policy->publish($userWithPublishPermission, $this->publishedEvent));
    }

    /** @test */
    public function user_with_custom_bookings_permission_can_manage_bookings(): void
    {
        // Create user with only VIEW_BOOKINGS permission (without any role that normally has bookings access)
        $userWithBookingsPermission = User::factory()->create();
        $this->organizer->users()->attach($userWithBookingsPermission->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([\App\Enums\OrganizerPermissionEnum::VIEW_BOOKINGS->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->policy->manageBookings($userWithBookingsPermission, $this->publishedEvent));
    }

    /** @test */
    public function user_with_custom_event_permissions_can_create_and_update_events(): void
    {
        // Create user with event management permissions but viewer role
        $userWithEventPermissions = User::factory()->create();
        $this->organizer->users()->attach($userWithEventPermissions->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([
                \App\Enums\OrganizerPermissionEnum::CREATE_EVENTS->value,
                \App\Enums\OrganizerPermissionEnum::EDIT_EVENTS->value,
            ]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $this->assertTrue($this->policy->create($userWithEventPermissions));
        $this->assertTrue($this->policy->update($userWithEventPermissions, $this->publishedEvent));
        $this->assertTrue($this->policy->manageOccurrences($userWithEventPermissions, $this->publishedEvent));
        $this->assertTrue($this->policy->manageMedia($userWithEventPermissions, $this->publishedEvent));
        $this->assertTrue($this->policy->duplicate($userWithEventPermissions, $this->publishedEvent));
    }

    /** @test */
    public function user_without_specific_permissions_cannot_perform_restricted_actions(): void
    {
        // Create user with minimal permissions
        $userWithMinimalPermissions = User::factory()->create();
        $this->organizer->users()->attach($userWithMinimalPermissions->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([\App\Enums\OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Should not be able to delete, publish, or manage events
        $this->assertFalse($this->policy->delete($userWithMinimalPermissions, $this->publishedEvent));
        $this->assertFalse($this->policy->publish($userWithMinimalPermissions, $this->publishedEvent));
        $this->assertFalse($this->policy->update($userWithMinimalPermissions, $this->publishedEvent));
        $this->assertFalse($this->policy->create($userWithMinimalPermissions));
    }
}
