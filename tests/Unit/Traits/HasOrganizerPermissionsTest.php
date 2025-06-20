<?php

namespace Tests\Unit\Traits;

use Tests\TestCase;
use App\Models\User;
use App\Models\Organizer;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class HasOrganizerPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Organizer $organizer;
    protected Organizer $otherOrganizer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required roles
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'organizer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->organizer = Organizer::factory()->create();
        $this->otherOrganizer = Organizer::factory()->create();
    }

    protected function tearDown(): void
    {
        // Clean up organizer memberships to prevent state contamination between tests
        if ($this->user) {
            $this->user->organizers()->detach();
        }

        parent::tearDown();
    }

    public function test_has_organizer_permission_returns_false_when_user_not_member(): void
    {
        $result = $this->user->hasOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS);

        $this->assertFalse($result);
    }

    public function test_has_organizer_permission_returns_true_for_role_based_permission(): void
    {
        // Attach user as owner (owners have all permissions by default)
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS);

        $this->assertTrue($result);
    }

    public function test_has_organizer_permission_returns_true_for_custom_permission(): void
    {
        // Attach user as viewer with custom create_events permission
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::CREATE_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS);

        $this->assertTrue($result);
    }

    public function test_has_organizer_permission_accepts_string_permission(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasOrganizerPermission($this->organizer, 'create_events');

        $this->assertTrue($result);
    }

    public function test_has_any_organizer_permission_returns_true_when_user_has_one_permission(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $permissions = [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::VIEW_EVENTS->value,
        ];

        $result = $this->user->hasAnyOrganizerPermission($this->organizer, $permissions);

        $this->assertTrue($result);
    }

    public function test_has_any_organizer_permission_returns_false_when_user_has_no_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $permissions = [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::EDIT_EVENTS->value,
        ];

        $result = $this->user->hasAnyOrganizerPermission($this->organizer, $permissions);

        $this->assertFalse($result);
    }

    public function test_has_all_organizer_permissions_returns_true_when_user_has_all_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $permissions = [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::EDIT_EVENTS->value,
        ];

        $result = $this->user->hasAllOrganizerPermissions($this->organizer, $permissions);

        $this->assertTrue($result);
    }

    public function test_has_all_organizer_permissions_returns_false_when_user_missing_one_permission(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $permissions = [
            OrganizerPermissionEnum::VIEW_EVENTS->value,
            OrganizerPermissionEnum::CREATE_EVENTS->value,
        ];

        $result = $this->user->hasAllOrganizerPermissions($this->organizer, $permissions);

        $this->assertFalse($result);
    }

    public function test_has_custom_organizer_permission_returns_true_for_custom_permission(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::CREATE_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasCustomOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS->value);

        $this->assertTrue($result);
    }

    public function test_has_custom_organizer_permission_returns_false_when_no_custom_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasCustomOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS->value);

        $this->assertFalse($result);
    }

    public function test_has_custom_organizer_permission_handles_array_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::CREATE_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasCustomOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS->value);

        $this->assertTrue($result);
    }

    public function test_has_role_based_organizer_permission_returns_true_for_role_permission(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->hasRoleBasedOrganizerPermission($this->organizer, OrganizerPermissionEnum::CREATE_EVENTS->value);

        $this->assertTrue($result);
    }

    public function test_get_organizer_permissions_returns_combined_role_and_custom_permissions(): void
    {
        $customPermissions = [OrganizerPermissionEnum::EXPORT_DATA->value];

        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'permissions' => json_encode($customPermissions),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $permissions = $this->user->getOrganizerPermissions($this->organizer);

        // Should include both role permissions and custom permissions
        $this->assertContains(OrganizerPermissionEnum::EXPORT_DATA->value, $permissions);
        $this->assertContains(OrganizerPermissionEnum::CREATE_EVENTS->value, $permissions); // Manager role permission
    }

    public function test_get_organizer_permissions_returns_empty_array_when_not_member(): void
    {
        $permissions = $this->user->getOrganizerPermissions($this->organizer);

        $this->assertEmpty($permissions);
    }

    public function test_get_organizer_permissions_by_category_groups_permissions_correctly(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $categorizedPermissions = $this->user->getOrganizerPermissionsByCategory($this->organizer);

        $this->assertArrayHasKey('settings', $categorizedPermissions);
        $this->assertArrayHasKey('users', $categorizedPermissions);
        $this->assertArrayHasKey('events', $categorizedPermissions);
        $this->assertArrayHasKey('venues', $categorizedPermissions);
        $this->assertArrayHasKey('analytics', $categorizedPermissions);
        $this->assertArrayHasKey('bookings', $categorizedPermissions);

        // Owner should have permissions in all categories
        $this->assertNotEmpty($categorizedPermissions['settings']);
        $this->assertNotEmpty($categorizedPermissions['events']);
    }

    public function test_can_manage_organizer_settings_returns_true_for_settings_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_ORGANIZER_SETTINGS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canManageOrganizerSettings($this->organizer);

        $this->assertTrue($result);
    }

    public function test_can_manage_organizer_team_returns_true_for_team_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_TEAM->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canManageOrganizerTeam($this->organizer);

        $this->assertTrue($result);
    }

    public function test_can_manage_organizer_events_returns_true_for_event_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::CREATE_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canManageOrganizerEvents($this->organizer);

        $this->assertTrue($result);
    }

    public function test_can_manage_organizer_venues_returns_true_for_venue_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::MANAGE_VENUES->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canManageOrganizerVenues($this->organizer);

        $this->assertTrue($result);
    }

    public function test_can_view_organizer_analytics_returns_true_for_analytics_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_ANALYTICS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canViewOrganizerAnalytics($this->organizer);

        $this->assertTrue($result);
    }

    public function test_can_manage_organizer_bookings_returns_true_for_booking_permissions(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::VIEW_BOOKINGS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $result = $this->user->canManageOrganizerBookings($this->organizer);

        $this->assertTrue($result);
    }

    public function test_get_organizers_with_permission_filters_correctly(): void
    {
        // User has create_events permission in first organizer but not second
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $this->user->organizers()->attach($this->otherOrganizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $organizers = $this->user->getOrganizersWithPermission(OrganizerPermissionEnum::CREATE_EVENTS);

        $this->assertCount(1, $organizers);
        $this->assertTrue($organizers->contains($this->organizer));
        $this->assertFalse($organizers->contains($this->otherOrganizer));
    }

    public function test_get_organizers_with_any_permission_filters_correctly(): void
    {
        // First organizer: VIEWER with additional CREATE_EVENTS permission
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => json_encode([OrganizerPermissionEnum::CREATE_EVENTS->value]),
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Second organizer: VIEWER with no additional permissions (but still has VIEW_EVENTS by default)
        $this->user->organizers()->attach($this->otherOrganizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Test for permissions that only the first organizer has
        $permissions = [
            OrganizerPermissionEnum::CREATE_EVENTS->value,
            OrganizerPermissionEnum::EDIT_EVENTS->value, // Neither has this
        ];

        $organizers = $this->user->getOrganizersWithAnyPermission($permissions);

        $this->assertCount(1, $organizers);
        $this->assertTrue($organizers->contains($this->organizer));
        $this->assertFalse($organizers->contains($this->otherOrganizer));
    }

    public function test_get_organizers_where_can_manage_events_filters_correctly(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $this->user->organizers()->attach($this->otherOrganizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::VIEWER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $organizers = $this->user->getOrganizersWhereCanManageEvents();

        $this->assertCount(1, $organizers);
        $this->assertTrue($organizers->contains($this->organizer));
    }

    public function test_get_organizers_where_can_manage_team_filters_correctly(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $this->user->organizers()->attach($this->otherOrganizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::STAFF->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        $organizers = $this->user->getOrganizersWhereCanManageTeam();

        $this->assertCount(1, $organizers);
        $this->assertTrue($organizers->contains($this->organizer));
    }

    public function test_organizer_membership_returns_correct_membership(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::OWNER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->user);
        $method = $reflection->getMethod('organizerMembership');
        $method->setAccessible(true);

        $membership = $method->invoke($this->user, $this->organizer);

        $this->assertNotNull($membership);
        $this->assertEquals($this->organizer->id, $membership->id);
        $this->assertEquals(OrganizerRoleEnum::OWNER->value, $membership->pivot->role_in_organizer);
    }

    public function test_get_organizer_role_returns_correct_role(): void
    {
        $this->user->organizers()->attach($this->organizer->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'permissions' => null,
            'joined_at' => now(),
            'is_active' => true,
        ]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->user);
        $method = $reflection->getMethod('getOrganizerRole');
        $method->setAccessible(true);

        $role = $method->invoke($this->user, $this->organizer);

        $this->assertEquals(OrganizerRoleEnum::MANAGER, $role);
    }

    public function test_get_organizer_role_returns_null_when_not_member(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->user);
        $method = $reflection->getMethod('getOrganizerRole');
        $method->setAccessible(true);

        $role = $method->invoke($this->user, $this->organizer);

        $this->assertNull($role);
    }
}
