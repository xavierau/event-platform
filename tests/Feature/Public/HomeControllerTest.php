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
use Carbon\Carbon;

class HomeControllerTest extends TestCase
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
        ];

        EventOccurrence::factory()->create(array_merge($defaultOccurrenceData, $occurrenceData));

        return $event;
    }

    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Public/Home'));
    }

    public function test_home_page_returns_separate_today_and_upcoming_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $tomorrow = now()->utc()->addDay();

        // Create event happening today
        $this->createTestEvent([], [
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        // Create event happening tomorrow (upcoming)
        $this->createTestEvent([], [
            'start_at_utc' => $tomorrow->copy()->addHours(10),
            'end_at_utc' => $tomorrow->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1)
                ->has('upcomingEvents', 2) // Both today and tomorrow events appear in upcoming
                ->has('moreEvents')
                ->has('initialCategories')
        );
    }

    public function test_home_page_shows_empty_today_events_when_none_exist(): void
    {
        // Create only future events (not today)
        $nextWeek = now()->utc()->addWeek();

        $this->createTestEvent([], [
            'start_at_utc' => $nextWeek->copy()->addHours(10),
            'end_at_utc' => $nextWeek->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 0) // No events today
                ->has('upcomingEvents', 1) // Future event appears in upcoming
        );
    }

    public function test_home_page_excludes_duplicate_events_from_more_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $nextWeek = now()->utc()->addWeek();

        // Create event happening today (will appear in both today and upcoming)
        $this->createTestEvent([], [
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        // Create event happening next week (will appear in upcoming)
        $this->createTestEvent([], [
            'start_at_utc' => $nextWeek->copy()->addHours(10),
            'end_at_utc' => $nextWeek->copy()->addHours(12),
        ]);

        // Create event happening far in the future (should appear in more events)
        $farFuture = now()->utc()->addMonths(2);
        $this->createTestEvent([], [
            'start_at_utc' => $farFuture->copy()->addHours(10),
            'end_at_utc' => $farFuture->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1)
                ->has('upcomingEvents', 2) // Today + next week events
                ->has('moreEvents', 1) // Only the far future event (others excluded as duplicates)
        );
    }

    public function test_home_page_only_includes_published_events(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::factory()->create();

        // Create published event
        $publishedEvent = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Published Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $publishedEvent->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
            'status' => 'scheduled',
        ]);

        // Create draft event (should not appear)
        $draftEvent = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft',
            'visibility' => 'public',
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $draftEvent->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(14),
            'end_at_utc' => $todayStart->copy()->addHours(16),
            'status' => 'scheduled',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1) // Only published event
                ->has('upcomingEvents', 1) // Only published event
        );
    }

    public function test_home_page_only_includes_scheduled_occurrences(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        // Create scheduled occurrence (should appear)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
            'status' => 'scheduled',
        ]);

        // Create cancelled occurrence (should not appear)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(14),
            'end_at_utc' => $todayStart->copy()->addHours(16),
            'status' => 'cancelled',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 1) // Only scheduled occurrence
                ->has('upcomingEvents', 1) // Only scheduled occurrence
        );
    }

    public function test_home_page_includes_categories(): void
    {
        // Create some categories
        Category::factory()->create(['name' => ['en' => 'Music'], 'slug' => 'music']);
        Category::factory()->create(['name' => ['en' => 'Theater'], 'slug' => 'theater']);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('initialCategories', 2)
                ->where('initialCategories.0.name', 'Music')
                ->where('initialCategories.1.name', 'Theater')
        );
    }

    public function test_home_page_event_data_structure(): void
    {
        $todayStart = now()->utc()->startOfDay();

        $this->createTestEvent([], [
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has(
                    'todayEvents.0',
                    fn($event) => $event
                        ->has('id')
                        ->has('name')
                        ->has('href')
                        ->has('image_url')
                        ->has('price_from')
                        ->has('price_to')
                        ->has('currency')
                        ->has('start_time')
                        ->has('venue_name')
                        ->has('category_name')
                )
                ->has(
                    'upcomingEvents.0',
                    fn($event) => $event
                        ->has('id')
                        ->has('name')
                        ->has('href')
                        ->has('image_url')
                        ->has('price_from')
                        ->has('price_to')
                        ->has('currency')
                        ->has('date_short')
                        ->has('category_name')
                )
        );
    }

    public function test_home_page_respects_event_limits(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $tomorrow = now()->utc()->addDay();

        // Create more events than the limits
        for ($i = 0; $i < 10; $i++) {
            // Today events
            $this->createTestEvent([], [
                'start_at_utc' => $todayStart->copy()->addHours(9 + $i),
                'end_at_utc' => $todayStart->copy()->addHours(10 + $i),
            ]);

            // Upcoming events
            $this->createTestEvent([], [
                'start_at_utc' => $tomorrow->copy()->addHours(9 + $i),
                'end_at_utc' => $tomorrow->copy()->addHours(10 + $i),
            ]);
        }

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 5) // Limited to 5 as per HomeController
                ->has('upcomingEvents', 15) // Limited to 15 as per HomeController
        );
    }

    public function test_home_page_handles_no_events_gracefully(): void
    {
        // Don't create any events
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) => $page
                ->component('Public/Home')
                ->has('todayEvents', 0)
                ->has('upcomingEvents', 0)
                ->has('moreEvents', 0)
        );
    }

    public function test_events_with_similar_dates_should_appear_together(): void
    {
        // Create events with dates close to each other, relative to now
        $date1 = now()->utc()->addDays(5)->startOfDay();
        $date2 = now()->utc()->addDays(10)->startOfDay();

        $event1 = $this->createTestEvent([
            'name' => ['en' => 'Event 1 - Upcoming Close Date'],
        ], [
            'start_at_utc' => $date1,
            'end_at_utc' => $date1->copy()->addHours(2),
        ]);

        $event2 = $this->createTestEvent([
            'name' => ['en' => 'Event 2 - Upcoming Close Date'],
        ], [
            'start_at_utc' => $date2,
            'end_at_utc' => $date2->copy()->addHours(2),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        // Both events should appear in the same section (upcoming events)
        // since they're both in the future and close in date
        $response->assertInertia(function ($page) use ($event1, $event2) {
            $upcomingEvents = $page->toArray()['props']['upcomingEvents'];
            $moreEvents = $page->toArray()['props']['moreEvents'];

            $upcomingEventIds = collect($upcomingEvents)->pluck('id')->toArray();
            // dump(['TEST_DEBUG_UPCOMING_EVENT_IDS' => $upcomingEventIds, 'EVENT1_ID' => $event1->id, 'EVENT2_ID' => $event2->id]);

            // Both events should appear in upcoming events
            $this->assertCount(2, $upcomingEvents, 'Both events should appear in upcoming events');
            $this->assertContains($event1->id, $upcomingEventIds);
            $this->assertContains($event2->id, $upcomingEventIds);

            // Check that the events are not duplicated in more events
            $moreEventIds = collect($moreEvents)->pluck('id')->toArray();
            $this->assertEmpty(
                array_intersect($upcomingEventIds, $moreEventIds),
                'Events should not appear in both upcoming and more events sections'
            );

            return $page->component('Public/Home');
        });
    }

    public function test_events_separated_by_30_day_window_issue(): void
    {
        // Clear any existing events to ensure clean test state
        Event::query()->delete();
        EventOccurrence::query()->delete();

        // This test demonstrates the actual production issue
        // Event 1: Within 30 days (goes to "Upcoming Events")
        $withinThirtyDays = now()->utc()->addDays(25); // 25 days from now

        // Event 2: Beyond 30 days (goes to "More Events")
        $beyondThirtyDays = now()->utc()->addDays(35); // 35 days from now

        $event1 = $this->createTestEvent([
            'name' => ['en' => 'Event Within 30 Days'],
        ], [
            'start_at_utc' => $withinThirtyDays,
            'end_at_utc' => $withinThirtyDays->copy()->addHours(2),
        ]);

        $event2 = $this->createTestEvent([
            'name' => ['en' => 'Event Beyond 30 Days'],
        ], [
            'start_at_utc' => $beyondThirtyDays,
            'end_at_utc' => $beyondThirtyDays->copy()->addHours(2),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        $response->assertInertia(function ($page) {
            $upcomingEvents = $page->toArray()['props']['upcomingEvents'];
            $moreEvents = $page->toArray()['props']['moreEvents'];

            $upcomingEventIds = collect($upcomingEvents)->pluck('id')->toArray();
            // dump(['TEST_DEBUG_UPCOMING_EVENT_IDS' => $upcomingEventIds, 'EVENT1_ID' => $event1->id, 'EVENT2_ID' => $event2->id]);

            // Debug output
            // echo "Upcoming Events: " . json_encode(collect($upcomingEvents)->pluck('name')) . PHP_EOL;
            // echo "More Events: " . json_encode(collect($moreEvents)->pluck('name')) . PHP_EOL;

            // This demonstrates the issue: events are separated by the 30-day window
            // Event 1 should be in upcoming events
            $upcomingEventNames = collect($upcomingEvents)->pluck('name')->toArray();
            $this->assertContains('Event Within 30 Days', $upcomingEventNames);

            // Event 2 should be in more events (this is the problematic behavior)
            $moreEventNames = collect($moreEvents)->pluck('name')->toArray();
            $this->assertContains('Event Beyond 30 Days', $moreEventNames);

            // This separation might not be ideal from a UX perspective
            // as both events are "upcoming" but get separated arbitrarily

            return $page->component('Public/Home');
        });
    }

    public function test_upcoming_events_window_can_be_configured(): void
    {
        // Clear any existing events to ensure clean test state
        Event::query()->delete();
        EventOccurrence::query()->delete();

        // Temporarily change the configuration to 45 days
        Config::set('app.upcoming_events_window_days', 45);

        // Event 1: Within 30 days (should be in upcoming events)
        $withinThirtyDays = now()->utc()->addDays(25);

        // Event 2: Within 45 days but beyond 30 days (should now also be in upcoming events)
        $withinFortyFiveDays = now()->utc()->addDays(35);

        // Event 3: Beyond 45 days (should be in more events)
        $beyondFortyFiveDays = now()->utc()->addDays(50);

        $event1 = $this->createTestEvent([
            'name' => ['en' => 'Event Within 30 Days'],
        ], [
            'start_at_utc' => $withinThirtyDays,
            'end_at_utc' => $withinThirtyDays->copy()->addHours(2),
        ]);

        $event2 = $this->createTestEvent([
            'name' => ['en' => 'Event Within 45 Days'],
        ], [
            'start_at_utc' => $withinFortyFiveDays,
            'end_at_utc' => $withinFortyFiveDays->copy()->addHours(2),
        ]);

        $event3 = $this->createTestEvent([
            'name' => ['en' => 'Event Beyond 45 Days'],
        ], [
            'start_at_utc' => $beyondFortyFiveDays,
            'end_at_utc' => $beyondFortyFiveDays->copy()->addHours(2),
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);

        $response->assertInertia(function ($page) {
            $upcomingEvents = $page->toArray()['props']['upcomingEvents'];
            $moreEvents = $page->toArray()['props']['moreEvents'];

            $upcomingEventNames = collect($upcomingEvents)->pluck('name')->toArray();
            $moreEventNames = collect($moreEvents)->pluck('name')->toArray();

            // With 45-day window, both events within 45 days should be in upcoming events
            $this->assertContains('Event Within 30 Days', $upcomingEventNames);
            $this->assertContains('Event Within 45 Days', $upcomingEventNames);

            // Only the event beyond 45 days should be in more events
            $this->assertContains('Event Beyond 45 Days', $moreEventNames);

            // Verify the events are not duplicated
            $this->assertNotContains('Event Within 30 Days', $moreEventNames);
            $this->assertNotContains('Event Within 45 Days', $moreEventNames);
            $this->assertNotContains('Event Beyond 45 Days', $upcomingEventNames);

            return $page->component('Public/Home');
        });
    }
}
