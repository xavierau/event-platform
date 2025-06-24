<?php

namespace Tests\Unit\Enums;

use Tests\TestCase;
use App\Enums\OrganizerPermissionEnum;
use App\Enums\OrganizerRoleEnum;

class OrganizerPermissionEnumTest extends TestCase
{
    public function test_all_returns_complete_permission_list(): void
    {
        $permissions = OrganizerPermissionEnum::all();

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);

        // Check that all enum cases are included
        $expectedCount = count(OrganizerPermissionEnum::cases());
        $this->assertCount($expectedCount, $permissions);

        // Verify specific permissions exist
        $this->assertContains('create_events', $permissions);
        $this->assertContains('edit_events', $permissions);
        $this->assertContains('view_analytics', $permissions);
        $this->assertContains('manage_team', $permissions);
    }

    public function test_by_category_returns_organized_permissions(): void
    {
        $categories = OrganizerPermissionEnum::byCategory();

        $this->assertIsArray($categories);
        $this->assertArrayHasKey('settings', $categories);
        $this->assertArrayHasKey('users', $categories);
        $this->assertArrayHasKey('events', $categories);
        $this->assertArrayHasKey('venues', $categories);
        $this->assertArrayHasKey('analytics', $categories);
        $this->assertArrayHasKey('bookings', $categories);

        // Verify each category contains permissions
        foreach ($categories as $category => $permissions) {
            $this->assertIsArray($permissions, "Category {$category} should be an array");
            $this->assertNotEmpty($permissions, "Category {$category} should not be empty");
        }
    }

    public function test_get_settings_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getSettingsPermissions();

        $this->assertContains('manage_organizer_settings', $permissions);
        $this->assertContains('edit_organizer_profile', $permissions);
        $this->assertContains('manage_settings', $permissions);
        $this->assertContains('manage_finances', $permissions);
    }

    public function test_get_user_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getUserPermissions();

        $this->assertContains('manage_team', $permissions);
        $this->assertContains('invite_users', $permissions);
        $this->assertContains('remove_users', $permissions);
        $this->assertContains('edit_team_roles', $permissions);
        $this->assertContains('view_team_members', $permissions);
    }

    public function test_get_event_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getEventPermissions();

        $this->assertContains('create_events', $permissions);
        $this->assertContains('edit_events', $permissions);
        $this->assertContains('delete_events', $permissions);
        $this->assertContains('view_events', $permissions);
        $this->assertContains('publish_events', $permissions);
        $this->assertContains('manage_event_occurrences', $permissions);
    }

    public function test_get_venue_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getVenuePermissions();

        $this->assertContains('manage_venues', $permissions);
        $this->assertContains('create_venues', $permissions);
        $this->assertContains('edit_venues', $permissions);
        $this->assertContains('delete_venues', $permissions);
        $this->assertContains('view_venues', $permissions);
    }

    public function test_get_analytics_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getAnalyticsPermissions();

        $this->assertContains('view_analytics', $permissions);
        $this->assertContains('view_reports', $permissions);
        $this->assertContains('export_data', $permissions);
        $this->assertContains('view_financial_reports', $permissions);
    }

    public function test_get_booking_permissions(): void
    {
        $permissions = OrganizerPermissionEnum::getBookingPermissions();

        $this->assertContains('view_bookings', $permissions);
        $this->assertContains('manage_bookings', $permissions);
        $this->assertContains('process_refunds', $permissions);
        $this->assertContains('manage_attendees', $permissions);
    }

    public function test_get_default_permissions_for_owner_role(): void
    {
        $permissions = OrganizerPermissionEnum::getDefaultPermissionsForRole(OrganizerRoleEnum::OWNER);

        $this->assertEquals(OrganizerPermissionEnum::all(), $permissions);
    }

    public function test_get_default_permissions_for_manager_role(): void
    {
        $permissions = OrganizerPermissionEnum::getDefaultPermissionsForRole(OrganizerRoleEnum::MANAGER);

        // Managers should have most permissions including settings management
        $this->assertContains('manage_team', $permissions);
        $this->assertContains('create_events', $permissions);
        $this->assertContains('edit_events', $permissions);
        $this->assertContains('view_analytics', $permissions);

        // Managers should have settings management permissions
        $this->assertContains('manage_organizer_settings', $permissions);
        $this->assertContains('edit_organizer_profile', $permissions);
        $this->assertContains('manage_settings', $permissions);

        // But should not have financial management
        $this->assertNotContains('manage_finances', $permissions);
    }

    public function test_get_default_permissions_for_staff_role(): void
    {
        $permissions = OrganizerPermissionEnum::getDefaultPermissionsForRole(OrganizerRoleEnum::STAFF);

        // Staff should have event management permissions
        $this->assertContains('create_events', $permissions);
        $this->assertContains('edit_events', $permissions);
        $this->assertContains('view_events', $permissions);

        // But not team management
        $this->assertNotContains('manage_team', $permissions);
        $this->assertNotContains('invite_users', $permissions);
        $this->assertNotContains('remove_users', $permissions);
    }

    public function test_get_default_permissions_for_viewer_role(): void
    {
        $permissions = OrganizerPermissionEnum::getDefaultPermissionsForRole(OrganizerRoleEnum::VIEWER);

        // Viewers should only have view permissions
        $this->assertContains('view_events', $permissions);
        $this->assertContains('view_venues', $permissions);
        $this->assertContains('view_analytics', $permissions);

        // But no management permissions
        $this->assertNotContains('create_events', $permissions);
        $this->assertNotContains('edit_events', $permissions);
        $this->assertNotContains('manage_team', $permissions);
    }

    public function test_is_in_category(): void
    {
        $permission = OrganizerPermissionEnum::CREATE_EVENTS;

        $this->assertTrue($permission->isInCategory('events'));
        $this->assertFalse($permission->isInCategory('users'));
        $this->assertFalse($permission->isInCategory('settings'));
    }

    public function test_get_category(): void
    {
        $this->assertEquals('events', OrganizerPermissionEnum::CREATE_EVENTS->getCategory());
        $this->assertEquals('users', OrganizerPermissionEnum::MANAGE_TEAM->getCategory());
        $this->assertEquals('settings', OrganizerPermissionEnum::MANAGE_FINANCES->getCategory());
        $this->assertEquals('venues', OrganizerPermissionEnum::CREATE_VENUES->getCategory());
        $this->assertEquals('analytics', OrganizerPermissionEnum::VIEW_ANALYTICS->getCategory());
        $this->assertEquals('bookings', OrganizerPermissionEnum::MANAGE_BOOKINGS->getCategory());
    }

    public function test_is_administrative(): void
    {
        // Administrative permissions
        $this->assertTrue(OrganizerPermissionEnum::MANAGE_ORGANIZER_SETTINGS->isAdministrative());
        $this->assertTrue(OrganizerPermissionEnum::MANAGE_FINANCES->isAdministrative());
        $this->assertTrue(OrganizerPermissionEnum::MANAGE_TEAM->isAdministrative());
        $this->assertTrue(OrganizerPermissionEnum::DELETE_EVENTS->isAdministrative());

        // Non-administrative permissions
        $this->assertFalse(OrganizerPermissionEnum::VIEW_EVENTS->isAdministrative());
        $this->assertFalse(OrganizerPermissionEnum::CREATE_EVENTS->isAdministrative());
        $this->assertFalse(OrganizerPermissionEnum::VIEW_ANALYTICS->isAdministrative());
    }

    public function test_is_view_only(): void
    {
        // View-only permissions
        $this->assertTrue(OrganizerPermissionEnum::VIEW_EVENTS->isViewOnly());
        $this->assertTrue(OrganizerPermissionEnum::VIEW_VENUES->isViewOnly());
        $this->assertTrue(OrganizerPermissionEnum::VIEW_ANALYTICS->isViewOnly());
        $this->assertTrue(OrganizerPermissionEnum::VIEW_BOOKINGS->isViewOnly());

        // Non-view-only permissions
        $this->assertFalse(OrganizerPermissionEnum::CREATE_EVENTS->isViewOnly());
        $this->assertFalse(OrganizerPermissionEnum::MANAGE_TEAM->isViewOnly());
        $this->assertFalse(OrganizerPermissionEnum::EDIT_EVENTS->isViewOnly());
    }

    public function test_permission_values_are_consistent(): void
    {
        // Test that permission values match their case names (snake_case)
        $this->assertEquals('create_events', OrganizerPermissionEnum::CREATE_EVENTS->value);
        $this->assertEquals('manage_team', OrganizerPermissionEnum::MANAGE_TEAM->value);
        $this->assertEquals('view_analytics', OrganizerPermissionEnum::VIEW_ANALYTICS->value);
        $this->assertEquals('manage_organizer_settings', OrganizerPermissionEnum::MANAGE_ORGANIZER_SETTINGS->value);
    }

    public function test_all_permissions_are_categorized(): void
    {
        $allPermissions = OrganizerPermissionEnum::all();
        $categorizedPermissions = [];

        foreach (OrganizerPermissionEnum::byCategory() as $permissions) {
            $categorizedPermissions = array_merge($categorizedPermissions, $permissions);
        }

        // Every permission should be in a category
        sort($allPermissions);
        sort($categorizedPermissions);

        $this->assertEquals($allPermissions, $categorizedPermissions);
    }

    public function test_no_duplicate_permissions_across_categories(): void
    {
        $allCategorizedPermissions = [];

        foreach (OrganizerPermissionEnum::byCategory() as $category => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertNotContains(
                    $permission,
                    $allCategorizedPermissions,
                    "Permission '{$permission}' appears in multiple categories"
                );
                $allCategorizedPermissions[] = $permission;
            }
        }
    }
}
