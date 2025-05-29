<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AllCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure necessary locales are available for translatable fields
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');
    }

    private function createTestEvent(array $eventData = [], array $occurrenceData = []): Event
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);

        // Use existing venue or create one with existing country to avoid constraint violations
        $venue = Venue::first() ?: Venue::factory()->create();

        $defaultEventData = [
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event'],
            'description' => ['en' => 'Event Description'],
            'short_summary' => ['en' => 'Short Summary'],
            'event_status' => 'published',
            'visibility' => 'public',
            'is_featured' => false,
            'cancellation_policy' => ['en' => 'Cancellation Policy'],
            'meta_title' => ['en' => 'Meta Title'],
            'meta_description' => ['en' => 'Meta Description'],
            'meta_keywords' => ['en' => 'keywords, test'],
        ];

        $event = Event::factory()->create(array_merge($defaultEventData, $eventData));

        $defaultOccurrenceData = [
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Test Occurrence'],
            'status' => 'scheduled',
            'timezone' => 'UTC',
            'start_at_utc' => now()->utc()->addDays(1),
            'end_at_utc' => now()->utc()->addDays(1)->addHours(2),
        ];

        EventOccurrence::factory()->create(array_merge($defaultOccurrenceData, $occurrenceData));

        return $event;
    }

    public function test_all_categories_shows_all_published_events(): void
    {
        // Create multiple categories
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        $sportsCategory = Category::factory()->create([
            'name' => ['en' => 'Sports'],
            'slug' => 'sports',
        ]);

        $theaterCategory = Category::factory()->create([
            'name' => ['en' => 'Theater'],
            'slug' => 'theater',
        ]);

        // Create events in different categories
        $musicEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Music Concert'],
        ]);

        $sportsEvent = $this->createTestEvent([
            'category_id' => $sportsCategory->id,
            'name' => ['en' => 'Football Match'],
        ]);

        $theaterEvent = $this->createTestEvent([
            'category_id' => $theaterCategory->id,
            'name' => ['en' => 'Theater Play'],
        ]);

        // Create a draft event (should not appear)
        $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft',
        ]);

        // Access the "All Categories" page (no category filter)
        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->where('title', 'All Events') // Default title when no category is selected
                ->has('events', 3) // Should show all 3 published events
                ->where('poster_url', null); // No poster URL for "All Categories"

            // Verify all published events are present
            $eventNames = collect($page->toArray()['props']['events'])->pluck('name')->toArray();
            $this->assertContains('Music Concert', $eventNames);
            $this->assertContains('Football Match', $eventNames);
            $this->assertContains('Theater Play', $eventNames);

            // Verify draft event is not present
            $this->assertNotContains('Draft Event', $eventNames);

            return $page;
        });
    }

    public function test_specific_category_shows_only_events_in_that_category(): void
    {
        // Create multiple categories
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        $sportsCategory = Category::factory()->create([
            'name' => ['en' => 'Sports'],
            'slug' => 'sports',
        ]);

        // Create events in different categories
        $musicEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Music Concert'],
        ]);

        $sportsEvent = $this->createTestEvent([
            'category_id' => $sportsCategory->id,
            'name' => ['en' => 'Football Match'],
        ]);

        // Access the Music category page
        $response = $this->get('/events?category=music');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->where('title', 'Music') // Category name as title
                ->has('events', 1); // Should show only 1 music event

            // Verify only music event is present
            $eventNames = collect($page->toArray()['props']['events'])->pluck('name')->toArray();
            $this->assertContains('Music Concert', $eventNames);
            $this->assertNotContains('Football Match', $eventNames);

            return $page;
        });
    }

    public function test_all_categories_respects_date_filtering(): void
    {
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        // Create event happening today
        $todayEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Today Event'],
        ], [
            'start_at_utc' => now()->utc()->startOfDay()->addHours(10),
            'end_at_utc' => now()->utc()->startOfDay()->addHours(12),
        ]);

        // Create event happening next week
        $nextWeekEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Next Week Event'],
        ], [
            'start_at_utc' => now()->utc()->addWeek(),
            'end_at_utc' => now()->utc()->addWeek()->addHours(2),
        ]);

        // Filter for today only
        $todayDate = now()->utc()->format('Y-m-d');
        $response = $this->get("/events?start={$todayDate}&end={$todayDate}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1); // Should show only today's event

            $eventNames = collect($page->toArray()['props']['events'])->pluck('name')->toArray();
            $this->assertContains('Today Event', $eventNames);
            $this->assertNotContains('Next Week Event', $eventNames);

            return $page;
        });
    }

    public function test_all_categories_only_shows_events_with_future_occurrences(): void
    {
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        // Create event with future occurrence
        $futureEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Future Event'],
        ], [
            'start_at_utc' => now()->utc()->addDays(1),
            'end_at_utc' => now()->utc()->addDays(1)->addHours(2),
        ]);

        // Create event with recent past occurrence (within 3 years - should appear)
        $pastEvent = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Past Event'],
        ], [
            'start_at_utc' => now()->utc()->subDays(1),
            'end_at_utc' => now()->utc()->subDays(1)->addHours(2),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->has('events', 2); // Should show both future and recent past events

            $eventNames = collect($page->toArray()['props']['events'])->pluck('name')->toArray();
            $this->assertContains('Future Event', $eventNames);
            $this->assertContains('Past Event', $eventNames);

            return $page;
        });
    }

    public function test_all_categories_event_data_structure(): void
    {
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        $event = $this->createTestEvent([
            'category_id' => $musicCategory->id,
            'name' => ['en' => 'Test Event'],
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1)
                ->has(
                    'events.0',
                    fn($event) => $event
                        ->has('id')
                        ->has('name')
                        ->has('href')
                        ->has('image_url')
                        ->has('price_from')
                        ->has('date_range')
                        ->has('venue_name')
                        ->has('category_name')
                        ->where('category_name', 'Music')
                );

            return $page;
        });
    }

    public function test_all_categories_handles_no_events_gracefully(): void
    {
        // Don't create any events
        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->where('title', 'All Events')
                ->has('events', 0); // No events should be shown

            return $page;
        });
    }

    public function test_all_categories_url_structure(): void
    {
        // Test that /events shows all categories
        $response = $this->get('/events');
        $response->assertStatus(200);

        // Test that /events?category=music shows specific category
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        $response = $this->get('/events?category=music');
        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->where('title', 'Music'));

        // Test that invalid category slug still works (shows all events)
        $response = $this->get('/events?category=non-existent');
        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->where('title', 'All Events'));
    }
}
