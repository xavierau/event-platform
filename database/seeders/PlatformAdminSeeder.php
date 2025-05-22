<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role; // Ensure this is uncommented

class PlatformAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password'), // Change password in production
                'email_verified_at' => now(),
            ]
        );

        if (class_exists(Role::class)) {
            $adminRole = Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web']);
            if (!$adminUser->hasRole('Platform Admin')) {
                $adminUser->assignRole($adminRole);
                $this->command->info('Platform Admin role assigned to admin@example.com.');
            } else {
                $this->command->info('admin@example.com already has Platform Admin role.');
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Skipping role assignment for Platform Admin.');
        }
    }
}
