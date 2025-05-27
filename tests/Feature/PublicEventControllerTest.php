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

class PublicEventControllerTest extends TestCase
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

        // Use existing venue or create one
        $venue = Venue::first() ?: Venue::factory()->create();

        $defaultEventData = [
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event-' . uniqid()],
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

    public function test_events_index_page_loads_successfully(): void
    {
        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Public/EventsByCategory'));
    }

    public function test_events_index_only_shows_published_events(): void
    {
        // Create a published event
        $publishedEvent = $this->createTestEvent([
            'name' => ['en' => 'Published Event'],
            'event_status' => 'published',
        ]);

        // Create a draft event (should not appear)
        $draftEvent = $this->createTestEvent([
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft',
        ]);

        // Create a cancelled event (should not appear)
        $cancelledEvent = $this->createTestEvent([
            'name' => ['en' => 'Cancelled Event'],
            'event_status' => 'cancelled',
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($publishedEvent) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1) // Only one published event should appear
                ->where('events.0.id', $publishedEvent->id)
                ->where('events.0.name', 'Published Event');
        });
    }

    public function test_events_index_only_shows_events_with_future_occurrences(): void
    {
        // Create event with future occurrence
        $futureEvent = $this->createTestEvent([
            'name' => ['en' => 'Future Event'],
        ], [
            'start_at_utc' => now()->utc()->addDays(1),
            'end_at_utc' => now()->utc()->addDays(1)->addHours(2),
        ]);

        // Create event with past occurrence
        $pastEvent = $this->createTestEvent([
            'name' => ['en' => 'Past Event'],
        ], [
            'start_at_utc' => now()->utc()->subDays(1),
            'end_at_utc' => now()->utc()->subDays(1)->addHours(2),
        ]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($futureEvent) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1) // Only future event should appear
                ->where('events.0.id', $futureEvent->id);
        });
    }

    public function test_event_show_returns_404_for_draft_event(): void
    {
        $draftEvent = $this->createTestEvent([
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft',
        ]);

        $response = $this->get("/events/{$draftEvent->id}");

        $response->assertStatus(404);
    }

    public function test_event_show_returns_404_for_cancelled_event(): void
    {
        $cancelledEvent = $this->createTestEvent([
            'name' => ['en' => 'Cancelled Event'],
            'event_status' => 'cancelled',
        ]);

        $response = $this->get("/events/{$cancelledEvent->id}");

        $response->assertStatus(404);
    }

    public function test_event_show_returns_404_for_nonexistent_event(): void
    {
        $response = $this->get('/events/999999');

        $response->assertStatus(404);
    }

    public function test_event_show_works_for_published_event_by_id(): void
    {
        $publishedEvent = $this->createTestEvent([
            'name' => ['en' => 'Published Event'],
            'event_status' => 'published',
        ]);

        $response = $this->get("/events/{$publishedEvent->id}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($publishedEvent) {
            $page->component('Public/EventDetail')
                ->where('event.id', $publishedEvent->id)
                ->where('event.name', 'Published Event');
        });
    }

    public function test_event_show_works_for_published_event_by_slug(): void
    {
        $publishedEvent = $this->createTestEvent([
            'name' => ['en' => 'Published Event'],
            'slug' => ['en' => 'published-event-slug'],
            'event_status' => 'published',
        ]);

        $response = $this->get('/events/published-event-slug');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($publishedEvent) {
            $page->component('Public/EventDetail')
                ->where('event.id', $publishedEvent->id)
                ->where('event.name', 'Published Event');
        });
    }

    public function test_events_index_filters_by_category(): void
    {
        $category1 = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music',
        ]);

        $category2 = Category::factory()->create([
            'name' => ['en' => 'Sports'],
            'slug' => 'sports',
        ]);

        // Create events in different categories
        $musicEvent = $this->createTestEvent([
            'category_id' => $category1->id,
            'name' => ['en' => 'Music Event'],
        ]);

        $sportsEvent = $this->createTestEvent([
            'category_id' => $category2->id,
            'name' => ['en' => 'Sports Event'],
        ]);

        // Test filtering by music category
        $response = $this->get('/events?category=music');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($musicEvent) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1)
                ->where('events.0.id', $musicEvent->id)
                ->where('title', 'Music');
        });
    }

    public function test_events_index_shows_all_events_when_no_category_filter(): void
    {
        $category1 = Category::factory()->create(['name' => ['en' => 'Music']]);
        $category2 = Category::factory()->create(['name' => ['en' => 'Sports']]);

        // Create events in different categories
        $musicEvent = $this->createTestEvent(['category_id' => $category1->id]);
        $sportsEvent = $this->createTestEvent(['category_id' => $category2->id]);

        $response = $this->get('/events');

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->has('events', 2); // Both events should appear
        });
    }

    public function test_events_index_handles_date_range_filtering(): void
    {
        // Create event for today
        $todayEvent = $this->createTestEvent([
            'name' => ['en' => 'Today Event'],
        ], [
            'start_at_utc' => now()->utc()->startOfDay()->addHours(10),
            'end_at_utc' => now()->utc()->startOfDay()->addHours(12),
        ]);

        // Create event for next week
        $nextWeekEvent = $this->createTestEvent([
            'name' => ['en' => 'Next Week Event'],
        ], [
            'start_at_utc' => now()->utc()->addWeek(),
            'end_at_utc' => now()->utc()->addWeek()->addHours(2),
        ]);

        // Filter for today only
        $today = now()->utc()->format('Y-m-d');
        $response = $this->get("/events?start={$today}&end={$today}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) use ($todayEvent) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1)
                ->where('events.0.id', $todayEvent->id);
        });
    }

    public function test_event_show_converts_utc_time_to_occurrence_timezone(): void
    {
        // Create event with specific timezone
        $publishedEvent = $this->createTestEvent([
            'name' => ['en' => 'Timezone Test Event'],
            'event_status' => 'published',
        ], [
            'start_at_utc' => '2025-05-28 06:00:00', // 6:00 AM UTC
            'end_at_utc' => '2025-05-28 08:00:00',   // 8:00 AM UTC
            'timezone' => 'Asia/Hong_Kong',          // UTC+8
        ]);

        $response = $this->get("/events/{$publishedEvent->id}");

        $response->assertStatus(200);
        $response->assertInertia(function ($page) {
            $page->component('Public/EventDetail')
                ->where('event.occurrences.0.full_date_time', '2025.05.28 Wednesday 14:00'); // Should be 14:00 (6:00 + 8 hours)
        });
    }
}
