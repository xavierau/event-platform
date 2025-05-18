<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()['cache']->forget('spatie.permission.cache');

        // Define Roles
        $roles = [
            'Platform Admin', // Super admin with all permissions
            'Organizer',      // Can manage their own events, venues, etc.
            'General User',   // Standard user, can purchase tickets, view events
            // Add any other roles you might need, e.g., 'Support Staff', 'Venue Manager'
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $this->command->info("Role '{$roleName}' created or ensured.");
        }

        // Define Permissions (example)
        // You can expand this significantly based on your application needs
        // $permissions = [
        //     'view events',
        //     'create events',
        //     'edit events',
        //     'delete events',
        //     'manage users',
        //     'manage site_settings',
        //     // etc.
        // ];

        // foreach ($permissions as $permissionName) {
        //     Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        // }
        // $this->command->info('Basic permissions created or ensured. Expand as needed.');

        // Assign Permissions to Roles (example)
        // $platformAdminRole = Role::findByName('Platform Admin');
        // if ($platformAdminRole) {
        //    // Platform Admin gets all permissions (Spatie typically handles this if using a Gate check like ->before())
        //    // Or assign all created permissions explicitly if preferred:
        //    // $platformAdminRole->givePermissionTo(Permission::all());
        //    $this->command->info('Permissions for Platform Admin can be set here (e.g., all permissions).');
        // }

        // $organizerRole = Role::findByName('Organizer');
        // if ($organizerRole) {
        //     // $organizerRole->givePermissionTo(['view events', 'create events', 'edit events', 'delete events']); // Example
        //     $this->command->info('Permissions for Organizer can be set here.');
        // }

        // $generalUserRole = Role::findByName('General User');
        // if ($generalUserRole) {
        //     // $generalUserRole->givePermissionTo(['view events']); // Example
        //     $this->command->info('Permissions for General User can be set here.');
        // }
        $this->command->info('Role creation complete. Permission definition and assignment logic is commented out; uncomment and configure as needed.');
    }
}
