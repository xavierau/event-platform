<?php

namespace App\Enums;

enum OrganizerPermissionEnum: string
{
    // Settings Management Permissions
    case MANAGE_ORGANIZER_SETTINGS = 'manage_organizer_settings';
    case EDIT_ORGANIZER_PROFILE = 'edit_organizer_profile';
    case MANAGE_SETTINGS = 'manage_settings';
    case MANAGE_FINANCES = 'manage_finances';

        // User/Team Management Permissions
    case MANAGE_TEAM = 'manage_team';
    case INVITE_USERS = 'invite_users';
    case REMOVE_USERS = 'remove_users';
    case EDIT_TEAM_ROLES = 'edit_team_roles';
    case VIEW_TEAM_MEMBERS = 'view_team_members';

        // Event Management Permissions
    case CREATE_EVENTS = 'create_events';
    case EDIT_EVENTS = 'edit_events';
    case DELETE_EVENTS = 'delete_events';
    case VIEW_EVENTS = 'view_events';
    case PUBLISH_EVENTS = 'publish_events';
    case MANAGE_EVENT_OCCURRENCES = 'manage_event_occurrences';

        // Venue Management Permissions
    case MANAGE_VENUES = 'manage_venues';
    case CREATE_VENUES = 'create_venues';
    case EDIT_VENUES = 'edit_venues';
    case DELETE_VENUES = 'delete_venues';
    case VIEW_VENUES = 'view_venues';

        // Analytics and Reporting Permissions
    case VIEW_ANALYTICS = 'view_analytics';
    case VIEW_REPORTS = 'view_reports';
    case EXPORT_DATA = 'export_data';
    case VIEW_FINANCIAL_REPORTS = 'view_financial_reports';

        // Booking Management Permissions
    case VIEW_BOOKINGS = 'view_bookings';
    case MANAGE_BOOKINGS = 'manage_bookings';
    case PROCESS_REFUNDS = 'process_refunds';
    case MANAGE_ATTENDEES = 'manage_attendees';

    /**
     * Get all permission values as an array.
     *
     * @return array<string>
     */
    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get permissions organized by categories.
     *
     * @return array<string, array<string>>
     */
    public static function byCategory(): array
    {
        return [
            'settings' => self::getSettingsPermissions(),
            'users' => self::getUserPermissions(),
            'events' => self::getEventPermissions(),
            'venues' => self::getVenuePermissions(),
            'analytics' => self::getAnalyticsPermissions(),
            'bookings' => self::getBookingPermissions(),
        ];
    }

    /**
     * Get settings management permissions.
     *
     * @return array<string>
     */
    public static function getSettingsPermissions(): array
    {
        return [
            self::MANAGE_ORGANIZER_SETTINGS->value,
            self::EDIT_ORGANIZER_PROFILE->value,
            self::MANAGE_SETTINGS->value,
            self::MANAGE_FINANCES->value,
        ];
    }

    /**
     * Get user/team management permissions.
     *
     * @return array<string>
     */
    public static function getUserPermissions(): array
    {
        return [
            self::MANAGE_TEAM->value,
            self::INVITE_USERS->value,
            self::REMOVE_USERS->value,
            self::EDIT_TEAM_ROLES->value,
            self::VIEW_TEAM_MEMBERS->value,
        ];
    }

    /**
     * Get event management permissions.
     *
     * @return array<string>
     */
    public static function getEventPermissions(): array
    {
        return [
            self::CREATE_EVENTS->value,
            self::EDIT_EVENTS->value,
            self::DELETE_EVENTS->value,
            self::VIEW_EVENTS->value,
            self::PUBLISH_EVENTS->value,
            self::MANAGE_EVENT_OCCURRENCES->value,
        ];
    }

    /**
     * Get venue management permissions.
     *
     * @return array<string>
     */
    public static function getVenuePermissions(): array
    {
        return [
            self::MANAGE_VENUES->value,
            self::CREATE_VENUES->value,
            self::EDIT_VENUES->value,
            self::DELETE_VENUES->value,
            self::VIEW_VENUES->value,
        ];
    }

    /**
     * Get analytics and reporting permissions.
     *
     * @return array<string>
     */
    public static function getAnalyticsPermissions(): array
    {
        return [
            self::VIEW_ANALYTICS->value,
            self::VIEW_REPORTS->value,
            self::EXPORT_DATA->value,
            self::VIEW_FINANCIAL_REPORTS->value,
        ];
    }

    /**
     * Get booking management permissions.
     *
     * @return array<string>
     */
    public static function getBookingPermissions(): array
    {
        return [
            self::VIEW_BOOKINGS->value,
            self::MANAGE_BOOKINGS->value,
            self::PROCESS_REFUNDS->value,
            self::MANAGE_ATTENDEES->value,
        ];
    }

    /**
     * Get default permissions for a specific role.
     *
     * @param OrganizerRoleEnum $role
     * @return array<string>
     */
    public static function getDefaultPermissionsForRole(OrganizerRoleEnum $role): array
    {
        return match ($role) {
            OrganizerRoleEnum::OWNER => self::all(), // Owners get all permissions
            OrganizerRoleEnum::MANAGER => [
                // Settings management (excluding financial management)
                self::MANAGE_ORGANIZER_SETTINGS->value,
                self::EDIT_ORGANIZER_PROFILE->value,
                self::MANAGE_SETTINGS->value,
                // Team management
                self::MANAGE_TEAM->value,
                self::INVITE_USERS->value,
                self::REMOVE_USERS->value,
                self::VIEW_TEAM_MEMBERS->value,
                // Event management
                self::CREATE_EVENTS->value,
                self::EDIT_EVENTS->value,
                self::DELETE_EVENTS->value,
                self::VIEW_EVENTS->value,
                self::PUBLISH_EVENTS->value,
                self::MANAGE_EVENT_OCCURRENCES->value,
                // Venue management
                self::VIEW_VENUES->value,
                self::EDIT_VENUES->value,
                // Analytics
                self::VIEW_ANALYTICS->value,
                self::VIEW_REPORTS->value,
                self::EXPORT_DATA->value,
                // Bookings
                self::VIEW_BOOKINGS->value,
                self::MANAGE_BOOKINGS->value,
                self::MANAGE_ATTENDEES->value,
            ],
            OrganizerRoleEnum::STAFF => [
                // Event management
                self::CREATE_EVENTS->value,
                self::EDIT_EVENTS->value,
                self::VIEW_EVENTS->value,
                self::MANAGE_EVENT_OCCURRENCES->value,
                // Limited venue access
                self::VIEW_VENUES->value,
                // Basic analytics
                self::VIEW_ANALYTICS->value,
                // Booking management
                self::VIEW_BOOKINGS->value,
                self::MANAGE_ATTENDEES->value,
                // Team viewing
                self::VIEW_TEAM_MEMBERS->value,
            ],
            OrganizerRoleEnum::VIEWER => [
                // View-only permissions
                self::VIEW_EVENTS->value,
                self::VIEW_VENUES->value,
                self::VIEW_ANALYTICS->value,
                self::VIEW_BOOKINGS->value,
                self::VIEW_TEAM_MEMBERS->value,
            ],
        };
    }

    /**
     * Check if this permission is related to a specific category.
     */
    public function isInCategory(string $category): bool
    {
        $categories = self::byCategory();
        return isset($categories[$category]) && in_array($this->value, $categories[$category]);
    }

    /**
     * Get the category this permission belongs to.
     */
    public function getCategory(): string
    {
        foreach (self::byCategory() as $category => $permissions) {
            if (in_array($this->value, $permissions)) {
                return $category;
            }
        }
        return 'unknown';
    }

    /**
     * Check if this is an administrative permission (high-level access required).
     */
    public function isAdministrative(): bool
    {
        return in_array($this->value, [
            self::MANAGE_ORGANIZER_SETTINGS->value,
            self::MANAGE_FINANCES->value,
            self::MANAGE_TEAM->value,
            self::EDIT_TEAM_ROLES->value,
            self::DELETE_EVENTS->value,
            self::DELETE_VENUES->value,
            self::PROCESS_REFUNDS->value,
        ]);
    }

    /**
     * Check if this is a view-only permission.
     */
    public function isViewOnly(): bool
    {
        return in_array($this->value, [
            self::VIEW_EVENTS->value,
            self::VIEW_VENUES->value,
            self::VIEW_ANALYTICS->value,
            self::VIEW_REPORTS->value,
            self::VIEW_BOOKINGS->value,
            self::VIEW_TEAM_MEMBERS->value,
            self::VIEW_FINANCIAL_REPORTS->value,
        ]);
    }
}
