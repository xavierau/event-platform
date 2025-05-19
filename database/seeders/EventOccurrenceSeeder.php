<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class EventOccurrenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure there are some Events and Venues to link to
        if (Event::count() === 0) {
            $this->command->info('No events found, creating some via EventFactory...');
            Event::factory(5)->create(); // Create some if none exist
        }
        if (Venue::count() === 0) {
            $this->command->info('No venues found, creating some via VenueFactory...');
            Venue::factory(3)->create(); // Create some if none exist
        }
        // Ensure TicketDefinitions are seeded or exist
        if (TicketDefinition::count() === 0) {
            $this->command->info('No ticket definitions found, running TicketDefinitionSeeder...');
            $this->call(TicketDefinitionSeeder::class);
        }

        $events = Event::all();
        $venues = Venue::all();
        $ticketDefinitions = TicketDefinition::all();

        if ($events->isEmpty() || $venues->isEmpty()) {
            $this->command->error('Cannot seed EventOccurrences: Missing events or venues.');
            return;
        }

        foreach ($events as $event) {
            // Create 1 to 3 occurrences for each event
            $numberOfOccurrences = rand(1, 3);
            // $event->refresh(); // Refresh didn't solve it, trying defensive access

            for ($i = 0; $i < $numberOfOccurrences; $i++) {
                $venue = $venues->random();
                $startsAt = now()->addDays(rand(1, 60))->addHours(rand(9, 18));
                $endsAt = $startsAt->copy()->addHours(rand(1, 4));

                // Defensive way to get English name
                $eventNameEn = 'Event'; // Default fallback
                if (is_array($event->name) && isset($event->name['en'])) {
                    $eventNameEn = $event->name['en'];
                } elseif (method_exists($event, 'getTranslation')) {
                    $eventNameEn = $event->getTranslation('name', 'en', false); // false to not use fallback locale if 'en' is missing
                    if (empty($eventNameEn) && !is_array($event->name)) { // If getTranslation returns empty and name is not array, it might be a simple string
                        $eventNameEn = (string) $event->name; // Try casting the whole thing if it was a string
                    }
                } elseif (!empty($event->name)) {
                    $eventNameEn = (string) $event->name; // Last resort if it's a non-empty scalar
                }
                if (empty(trim($eventNameEn))) {
                    $eventNameEn = 'Fallback Event Name';
                } // Ensure not empty

                /** @var EventOccurrence $occurrence */
                $occurrence = EventOccurrence::factory()->create([
                    'event_id' => $event->id,
                    'venue_id' => $venue->id,
                    'name' => ['en' => 'Occurrence ' . ($i + 1) . ' for ' . $eventNameEn],
                    'start_at_utc' => $startsAt,
                    'end_at_utc' => $endsAt,
                    'status' => Arr::random(['scheduled', 'active', 'cancelled', 'completed']),
                ]);

                // Associate some ticket definitions with this occurrence
                if ($ticketDefinitions->isNotEmpty()) {
                    $ticketDefinitionsToAttach = $ticketDefinitions->random(rand(1, min(3, $ticketDefinitions->count())));
                    foreach ($ticketDefinitionsToAttach as $ticketDef) {
                        $occurrence->ticketDefinitions()->attach($ticketDef->id, [
                            // Optional: Define pivot data here if your pivot table has extra columns
                            // 'quantity_for_sale' => rand(50, 200),
                            // 'sale_price_override' => null, // Or a specific price
                        ]);
                    }
                    $this->command->info("Attached " . $ticketDefinitionsToAttach->count() . " ticket definitions to occurrence ID: {$occurrence->id}");
                }
            }
        }
        $this->command->info("EventOccurrence seeding completed. Total occurrences: " . EventOccurrence::count());
    }
}
