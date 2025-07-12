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

        // Define roles with their descriptions
        $roles = [
            RoleNameEnum::ADMIN->value => 'Administrator',
            RoleNameEnum::USER->value => 'Regular User',
        ];

        foreach ($roles as $roleValue => $displayName) {
            Role::firstOrCreate(['name' => $roleValue, 'guard_name' => 'web']);
            $this->command->info("Role '{$displayName}' ({$roleValue}) created or ensured.");
        }

        // Assign Permissions to Roles
        $platformAdminRole = Role::findByName(RoleNameEnum::ADMIN->value, 'web');
        if ($platformAdminRole) {
            // Ensure the new 'manage-users' permission exists
            Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
            $this->command->info("Permission 'manage-users' created or ensured.");

            // Platform Admin gets all permissions
            $allPermissions = Permission::all();
            $platformAdminRole->givePermissionTo($allPermissions);
            $this->command->info('All permissions assigned to Platform Admin.');
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
