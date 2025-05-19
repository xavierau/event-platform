<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure some organizers (users) and categories exist
        if (User::whereHas('roles', fn($query) => $query->whereIn('name', ['organizer', 'platform_admin']))->count() === 0) {
            $this->command->info('No organizers or admins found, running OrganizerSeeder/PlatformAdminSeeder might be needed first or creating some directly.');
            // For simplicity, create a fallback organizer if absolutely none found from typical roles.
            User::factory()->create(); // This user might not have an organizer role unless factory assigns it.
        }
        if (Category::count() === 0) {
            $this->command->info('No categories found, running CategorySeeder might be needed first or creating some directly.');
            Category::factory(3)->create();
        }

        $organizers = User::whereHas('roles', fn($query) => $query->whereIn('name', ['organizer', 'platform_admin']))->get();
        if ($organizers->isEmpty()) { // Fallback if the role-based query still yields no one
            $organizers = User::all();
        }
        $categories = Category::all();

        if ($organizers->isEmpty() || $categories->isEmpty()) {
            $this->command->error('Cannot seed Events: Missing organizers or categories. Please run User and Category seeders first.');
            return;
        }

        // Create a few events
        Event::factory(10)->create([
            // Override factory defaults for each created event if needed, or let factory handle it
            // Example of overriding within a loop if more control is needed:
            // 'organizer_id' => $organizers->random()->id,
            // 'category_id' => $categories->random()->id,
        ]);

        $this->command->info('Event seeding completed. Total events: ' . Event::count());
    }
}
