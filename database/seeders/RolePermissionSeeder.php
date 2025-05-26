<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Enums\RoleNameEnum;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Define Roles using Enum
        $roles = [
            RoleNameEnum::ADMIN->value => 'Platform Admin',
            RoleNameEnum::ORGANIZER->value => 'Organizer',
            RoleNameEnum::USER->value => 'General User',
        ];

        foreach ($roles as $roleValue => $displayName) {
            Role::firstOrCreate(['name' => $roleValue, 'guard_name' => 'web']);
            $this->command->info("Role '{$displayName}' ({$roleValue}) created or ensured.");
        }

        // Assign Permissions to Roles
        $platformAdminRole = Role::findByName(RoleNameEnum::ADMIN->value, 'web');
        if ($platformAdminRole) {
            // Platform Admin gets all permissions
            $allPermissions = Permission::all();
            $platformAdminRole->givePermissionTo($allPermissions);
            $this->command->info('All permissions assigned to Platform Admin.');
        }

        $organizerRole = Role::findByName(RoleNameEnum::ORGANIZER->value, 'web');
        if ($organizerRole) {
            $organizerPermissions = [
                'viewAny Event',
                'view Event',
                'create Event',
                'update Event',
                'delete Event',
                'viewAny EventOccurrence',
                'view EventOccurrence',
                'create EventOccurrence',
                'update EventOccurrence',
                'delete EventOccurrence',
                'viewAny Venue',
                'view Venue',
                'create Venue',
                'update Venue',
                'delete Venue',
                'viewAny Booking',
                'view Booking',
                'viewAny TicketDefinition',
                'view TicketDefinition',
                'create TicketDefinition',
                'update TicketDefinition',
                'delete TicketDefinition',
                'viewAny Tag',
                'view Tag',
                'viewAny Category',
                'view Category',
            ];
            foreach ($organizerPermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']); // Ensure permission exists
            }
            $organizerRole->givePermissionTo($organizerPermissions);
            $this->command->info('Permissions assigned to Organizer.');
        }

        $generalUserRole = Role::findByName(RoleNameEnum::USER->value, 'web');
        if ($generalUserRole) {
            $generalUserPermissions = [
                'viewAny Event',
                'view Event',
                'viewAny EventOccurrence',
                'view EventOccurrence',
                'viewAny Venue',
                'view Venue',
                'viewAny Booking', // Typically for their own bookings, handled by policy/query scope
                'view Booking',    // Typically for their own bookings, handled by policy/query scope
                'create Booking',
                'viewAny Tag',
                'view Tag',
                'viewAny Category',
                'view Category',
            ];
            foreach ($generalUserPermissions as $permissionName) {
                Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']); // Ensure permission exists
            }
            $generalUserRole->givePermissionTo($generalUserPermissions);
            $this->command->info('Permissions assigned to General User.');
        }

        $this->command->info('Role creation and permission assignment complete.');
    }
}
