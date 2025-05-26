<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Enums\RoleNameEnum;

class GeneralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $generalUserRole = null;
        $generalUserRoleName = RoleNameEnum::USER->value;

        if (class_exists(Role::class)) {
            $generalUserRole = Role::where('name', $generalUserRoleName)
                ->where('guard_name', 'web')
                ->first();

            if (!$generalUserRole) {
                $this->command->error("Role '{$generalUserRoleName}' not found. Make sure RolePermissionSeeder has run and created it using the Enum.");
            }
        } else {
            $this->command->error('Spatie\Permission\Models\Role class not found. Cannot assign General User role.');
        }

        // Create a few general users with specific email patterns
        $users = collect();
        for ($i = 1; $i <= 10; $i++) {
            $users->push(User::factory()->create([
                'email' => "user{$i}@example.com",
            ]));
        }

        if ($generalUserRole) {
            $users->each(function (User $user) use ($generalUserRole, $generalUserRoleName) {
                if (!$user->hasRole($generalUserRoleName)) {
                    $user->assignRole($generalUserRole);
                }
            });
            $this->command->info("10 General Users created via factory and assigned '{$generalUserRoleName}' role.");
        } else {
            $this->command->info("10 General Users created via factory. Role '{$generalUserRoleName}' not found, so not assigned.");
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
