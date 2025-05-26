<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Enums\RoleNameEnum;

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
        $organizerRoleName = RoleNameEnum::ORGANIZER->value;

        if (class_exists(Role::class)) {
            $organizerRole = Role::where('name', $organizerRoleName)->where('guard_name', 'web')->first();
            if (!$organizerRole) {
                $this->command->error("Role '{$organizerRoleName}' not found. Make sure RolePermissionSeeder has run and created it using the Enum.");
                // Optionally create it if it must exist, though RolePermissionSeeder should be the source of truth.
                // $organizerRole = Role::firstOrCreate(['name' => $organizerRoleName, 'guard_name' => 'web']);
                // $this->command->warn("Role '{$organizerRoleName}' was not found, created it as a fallback.");
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Cannot assign Organizer role.');
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

            if ($organizerRole && $organizerUser->exists && !$organizerUser->hasRole($organizerRoleName)) {
                $organizerUser->assignRole($organizerRole);
                $this->command->info("Assigned '{$organizerRoleName}' role to {$data['email']}.");
            } elseif ($organizerRole && $organizerUser->hasRole($organizerRoleName)) {
                $this->command->info("User {$data['email']} already has '{$organizerRoleName}' role.");
            } elseif (!$organizerRole) {
                $this->command->warn("Could not assign '{$organizerRoleName}' role to {$data['email']} because role was not found.");
            }
        }
        $this->command->info('Organizer users seeding complete.');
    }
}
