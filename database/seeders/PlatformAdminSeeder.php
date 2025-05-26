<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Enums\RoleNameEnum; // Import the Enum

class PlatformAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = 'admin@example.com';
        $adminName = 'Platform Admin';

        $adminUser = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (class_exists(Role::class)) {
            // Ensure the role exists using the Enum value
            $adminRole = Role::firstOrCreate(['name' => RoleNameEnum::ADMIN->value, 'guard_name' => 'web']);

            if (!$adminUser->hasRole(RoleNameEnum::ADMIN->value)) {
                $adminUser->assignRole($adminRole); // Can also pass Enum value directly: RoleNameEnum::ADMIN->value
                $this->command->info("Role '" . RoleNameEnum::ADMIN->value . "' assigned to {$adminEmail}.");
            } else {
                $this->command->info("{$adminEmail} already has the '" . RoleNameEnum::ADMIN->value . "' role.");
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Skipping role assignment for Platform Admin.');
        }
    }
}
