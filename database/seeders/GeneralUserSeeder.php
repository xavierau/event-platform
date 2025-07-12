<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GeneralUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'General User 1', 'email' => 'user1@example.com', 'password' => 'password'],
            ['name' => 'General User 2', 'email' => 'user2@example.com', 'password' => 'password'],
            ['name' => 'General User 3', 'email' => 'user3@example.com', 'password' => 'password'],
            ['name' => 'General User 4', 'email' => 'user4@example.com', 'password' => 'password'],
            ['name' => 'General User 5', 'email' => 'user5@example.com', 'password' => 'password'],
        ];

        foreach ($users as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                ['name' => $userData['name'], 'password' => Hash::make($userData['password'])]
            );

            // Assign the 'user' role if the user was just created and doesn't have it.
            if ($user->wasRecentlyCreated && !$user->hasRole('user')) {
                $user->assignRole('user');
            }
        }

        // Create 15 more random users and assign them the 'user' role.
        User::factory()->count(15)->create()->each(function ($user) {
            if (!$user->hasRole('user')) {
                $user->assignRole('user');
            }
        });

        $this->command->info('General users seeded successfully.');
    }
}
