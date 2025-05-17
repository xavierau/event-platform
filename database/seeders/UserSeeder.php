<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Define the admin user's details
        $adminEmail = 'admin@admin.com';
        $adminName = 'Administrator';
        $adminPassword = 'password';
        $adminRoleName = 'platform-admin';

        // 1. Create or find the 'platform-admin' role
        $role = Role::firstOrCreate(['name' => $adminRoleName, 'guard_name' => 'web']);

        // 2. Create or update the admin user
        $user = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // 3. Assign the role to the user
        if (!$user->hasRole($adminRoleName)) {
            $user->assignRole($role);
        }

        $this->command->info('Admin user created/updated and assigned role: ' . $adminRoleName);
    }
}
