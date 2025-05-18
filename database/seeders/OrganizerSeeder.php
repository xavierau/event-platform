<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class OrganizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $organizerRole = null;
        if (class_exists(Role::class)) {
            // Role should be created by RolePermissionSeeder, so we find it here.
            $organizerRole = Role::where('name', 'Organizer')->where('guard_name', 'web')->first();
            if (!$organizerRole) {
                $this->command->error("Role 'Organizer' not found. Make sure RolePermissionSeeder has run.");
                // Optionally, create it as a fallback, though less clean:
                // $organizerRole = Role::firstOrCreate(['name' => 'Organizer', 'guard_name' => 'web']);
                // $this->command->warn("Role 'Organizer' was not found, created it as a fallback.");
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Cannot assign Organizer role.');
            // Return or exit if role assignment is critical and Spatie roles are not available
        }

        $organizersData = [
            [
                'name' => 'Event Corp',
                'email' => 'organizer1@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Music Fest Group',
                'email' => 'organizer2@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        ];

        foreach ($organizersData as $data) {
            $organizerUser = User::firstOrCreate(
                ['email' => $data['email']],
                $data
            );

            if ($organizerRole && $organizerUser->exists && !$organizerUser->hasRole('Organizer')) {
                $organizerUser->assignRole($organizerRole);
                $this->command->info("Assigned 'Organizer' role to {$data['email']}.");
            } elseif ($organizerRole && $organizerUser->hasRole('Organizer')) {
                $this->command->info("User {$data['email']} already has 'Organizer' role.");
            } elseif (!$organizerRole) {
                $this->command->warn("Could not assign 'Organizer' role to {$data['email']} because role was not found.");
            }
        }
        $this->command->info('Organizer users seeding complete.');
    }
}
