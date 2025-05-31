<?php

namespace Tests\Support\Traits;

use App\Models\Category;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Models\Venue;
use Carbon\Carbon;

trait CreatesTestData
{
    /**
     * Create a complete event with all related data
     */
    protected function createCompleteEvent(array $eventAttributes = [], array $occurrenceAttributes = [], array $ticketAttributes = []): Event
    {
        $venue = $this->createTestVenue();
        $category = $this->createTestCategory();

        $event = Event::factory()->create(array_merge([
            'venue_id' => $venue->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'published_at' => now(),
        ], $eventAttributes));

        $occurrence = $this->createTestOccurrence($event, $venue, $occurrenceAttributes);
        $this->createTestTickets($occurrence, $ticketAttributes);

        return $event->load(['eventOccurrences.ticketDefinitions', 'category', 'eventOccurrences.venue']);
    }

    /**
     * Create a test venue with country
     */
    protected function createTestVenue(array $attributes = []): Venue
    {
        $country = Country::factory()->create();

        return Venue::factory()->create(array_merge([
            'country_id' => $country->id,
        ], $attributes));
    }

    /**
     * Create a test category
     */
    protected function createTestCategory(array $attributes = []): Category
    {
        return Category::factory()->create($attributes);
    }

    /**
     * Create a test event occurrence
     */
    protected function createTestOccurrence(Event $event, Venue $venue, array $attributes = []): EventOccurrence
    {
        return EventOccurrence::factory()->create(array_merge([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'occurrence_status' => 'upcoming',
            'start_datetime' => Carbon::now()->addDays(7),
            'end_datetime' => Carbon::now()->addDays(7)->addHours(2),
        ], $attributes));
    }

    /**
     * Create test tickets for an occurrence
     */
    protected function createTestTickets(EventOccurrence $occurrence, array $attributes = []): void
    {
        $ticketDefinitions = TicketDefinition::factory()->count(3)->create($attributes);

        foreach ($ticketDefinitions as $ticket) {
            $occurrence->ticketDefinitions()->attach($ticket->id, [
                'price_override' => null,
                'quantity_available' => 100,
            ]);
        }
    }

    /**
     * Create an admin user with permissions
     */
    protected function createAdminUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole('admin');
        return $user;
    }

    /**
     * Create a regular user
     */
    protected function createRegularUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /**
     * Create events for different time periods
     */
    protected function createEventsForTimePeriods(): array
    {
        return [
            'past' => $this->createCompleteEvent(
                ['event_status' => 'completed'],
                [
                    'start_datetime' => Carbon::now()->subDays(7),
                    'end_datetime' => Carbon::now()->subDays(7)->addHours(2),
                    'occurrence_status' => 'completed'
                ]
            ),
            'today' => $this->createCompleteEvent(
                ['event_status' => 'published'],
                [
                    'start_datetime' => Carbon::today()->addHours(14),
                    'end_datetime' => Carbon::today()->addHours(16),
                    'occurrence_status' => 'upcoming'
                ]
            ),
            'upcoming' => $this->createCompleteEvent(
                ['event_status' => 'published'],
                [
                    'start_datetime' => Carbon::now()->addDays(15),
                    'end_datetime' => Carbon::now()->addDays(15)->addHours(2),
                    'occurrence_status' => 'upcoming'
                ]
            ),
            'far_future' => $this->createCompleteEvent(
                ['event_status' => 'published'],
                [
                    'start_datetime' => Carbon::now()->addDays(45),
                    'end_datetime' => Carbon::now()->addDays(45)->addHours(2),
                    'occurrence_status' => 'upcoming'
                ]
            ),
        ];
    }
}
