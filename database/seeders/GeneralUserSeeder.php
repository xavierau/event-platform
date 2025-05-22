<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role; // Ensure this is uncommented

class GeneralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generalUserRole = null;
        if (class_exists(Role::class)) {
            $generalUserRole = Role::where('name', 'General User')->where('guard_name', 'web')->first();
            if (!$generalUserRole) {
                $this->command->error("Role 'General User' not found. Make sure RolePermissionSeeder has run.");
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Cannot assign General User role.');
        }

        // Create a few general users using factory
        $users = User::factory()->count(10)->create();

        if ($generalUserRole) {
            $users->each(function (User $user) use ($generalUserRole) {
                if (!$user->hasRole('General User')) {
                    $user->assignRole($generalUserRole);
                }
            });
            $this->command->info('10 General Users created via factory and assigned \'General User\' role.');
        } else {
            $this->command->info('10 General Users created via factory. Role \'General User\' not found, so not assigned.');
        }

        // Example of creating a specific general user (can be uncommented and adapted)
        /*
        $specificUserData = [
            'name' => 'Alice Wonderland',
            'email' => 'alice@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ];
        $alice = User::firstOrCreate(['email' => $specificUserData['email']], $specificUserData);
        if ($generalUserRole && !$alice->hasRole('General User')) {
            $alice->assignRole($generalUserRole);
        }
        */
    }
}
