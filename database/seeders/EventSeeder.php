<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Organizer;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure organizers and categories exist
        if (Organizer::where('is_active', true)->count() === 0) {
            $this->command->info('No active organizers found. Running OrganizerSeeder first...');
            $this->call(OrganizerSeeder::class);
        }

        if (Category::count() === 0) {
            $this->command->info('No categories found. Running CategorySeeder first...');
            $this->call(CategorySeeder::class);
        }

        $organizers = Organizer::where('is_active', true)->get();
        $categories = Category::all();

        if ($organizers->isEmpty() || $categories->isEmpty()) {
            $this->command->error('Cannot seed Events: Missing organizers or categories. Please run Organizer and Category seeders first.');
            return;
        }

        // Create events with proper organizer relationships
        Event::factory(10)->make()->each(function ($event) use ($organizers, $categories) {
            $event->organizer_id = $organizers->random()->id;
            $event->category_id = $categories->random()->id;
            $event->save();
        });

        $this->command->info('Event seeding completed successfully.');
        $this->command->info('Total events: ' . Event::count());
        $this->command->info('Events distributed across ' . $organizers->count() . ' organizers');
    }
}
