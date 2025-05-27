<?php

namespace Tests\Unit\Services;

use App\Actions\Event\UpsertEventAction;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    private EventService $eventService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventService = new EventService(new UpsertEventAction());

        // Ensure necessary locales are available for translatable fields
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');
    }

    private function createTestEvent(array $occurrenceData = []): Event
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);

        // Use existing venue or create one with existing country to avoid constraint violations
        $venue = Venue::first() ?: Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event'],
            'description' => ['en' => 'Event Description'],
            'short_summary' => ['en' => 'Short Summary'],
            'event_status' => 'published', // Important: must be published to appear in results
            'visibility' => 'public',
            'is_featured' => false,
            'cancellation_policy' => ['en' => 'Cancellation Policy'],
            'meta_title' => ['en' => 'Meta Title'],
            'meta_description' => ['en' => 'Meta Description'],
            'meta_keywords' => ['en' => 'keywords, test'],
        ]);

        // Create event occurrence with provided data
        $defaultOccurrenceData = [
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Test Occurrence'],
            'status' => 'scheduled',
            'timezone' => 'UTC',
        ];

        $mergedData = array_merge($defaultOccurrenceData, $occurrenceData);

        EventOccurrence::factory()->create($mergedData);

        return $event;
    }

    public function test_getEventsToday_returns_events_happening_today(): void
    {
        // Create events for different dates
        $todayStart = now()->utc()->startOfDay();
        $todayEnd = now()->utc()->endOfDay();
        $tomorrow = now()->utc()->addDay()->startOfDay();
        $yesterday = now()->utc()->subDay()->startOfDay();

        // Event happening today (morning)
        $this->createTestEvent([
            'start_at_utc' => $todayStart->copy()->addHours(9),
            'end_at_utc' => $todayStart->copy()->addHours(11),
        ]);

        // Event happening today (evening)
        $this->createTestEvent([
            'start_at_utc' => $todayEnd->copy()->subHours(2),
            'end_at_utc' => $todayEnd->copy()->subHour(),
        ]);

        // Event happening tomorrow (should not be included)
        $this->createTestEvent([
            'start_at_utc' => $tomorrow->copy()->addHours(10),
            'end_at_utc' => $tomorrow->copy()->addHours(12),
        ]);

        // Event happened yesterday (should not be included)
        $this->createTestEvent([
            'start_at_utc' => $yesterday->copy()->addHours(10),
            'end_at_utc' => $yesterday->copy()->addHours(12),
        ]);

        $todayEvents = $this->eventService->getEventsToday(10);

        $this->assertCount(2, $todayEvents, 'Should return exactly 2 events happening today');

        // Verify the structure of returned events
        foreach ($todayEvents as $event) {
            $this->assertArrayHasKey('id', $event);
            $this->assertArrayHasKey('name', $event);
            $this->assertArrayHasKey('href', $event);
            $this->assertArrayHasKey('image_url', $event);
            $this->assertArrayHasKey('price_from', $event);
            $this->assertArrayHasKey('price_to', $event);
            $this->assertArrayHasKey('currency', $event);
            $this->assertArrayHasKey('start_time', $event);
            $this->assertArrayHasKey('venue_name', $event);
            $this->assertArrayHasKey('category_name', $event);
        }
    }

    public function test_getEventsToday_respects_limit_parameter(): void
    {
        // Create 5 events happening today
        for ($i = 0; $i < 5; $i++) {
            $this->createTestEvent([
                'start_at_utc' => now()->utc()->startOfDay()->addHours(9 + $i),
                'end_at_utc' => now()->utc()->startOfDay()->addHours(10 + $i),
            ]);
        }

        $limitedEvents = $this->eventService->getEventsToday(3);

        $this->assertCount(3, $limitedEvents, 'Should respect the limit parameter');
    }

    public function test_getEventsToday_returns_empty_array_when_no_events_today(): void
    {
        // Create events for tomorrow and yesterday, but none for today
        $tomorrow = now()->utc()->addDay()->startOfDay();
        $yesterday = now()->utc()->subDay()->startOfDay();

        $this->createTestEvent([
            'start_at_utc' => $tomorrow->copy()->addHours(10),
            'end_at_utc' => $tomorrow->copy()->addHours(12),
        ]);

        $this->createTestEvent([
            'start_at_utc' => $yesterday->copy()->addHours(10),
            'end_at_utc' => $yesterday->copy()->addHours(12),
        ]);

        $todayEvents = $this->eventService->getEventsToday(10);

        $this->assertEmpty($todayEvents, 'Should return empty array when no events today');
    }

    public function test_getEventsToday_only_includes_published_events(): void
    {
        $todayStart = now()->utc()->startOfDay();

        // Create published event
        $this->createTestEvent([
            'start_at_utc' => $todayStart->copy()->addHours(9),
            'end_at_utc' => $todayStart->copy()->addHours(11),
        ]);

        // Create draft event (should not be included)
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::first() ?: Venue::factory()->create();

        $draftEvent = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Draft Event'],
            'event_status' => 'draft', // Not published
            'visibility' => 'public',
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $draftEvent->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(10),
            'end_at_utc' => $todayStart->copy()->addHours(12),
            'status' => 'scheduled',
        ]);

        $todayEvents = $this->eventService->getEventsToday(10);

        $this->assertCount(1, $todayEvents, 'Should only include published events');
    }

    public function test_getEventsToday_only_includes_scheduled_occurrences(): void
    {
        $todayStart = now()->utc()->startOfDay();
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::first() ?: Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'visibility' => 'public',
        ]);

        // Create scheduled occurrence (should be included)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(9),
            'end_at_utc' => $todayStart->copy()->addHours(11),
            'status' => 'scheduled',
        ]);

        // Create cancelled occurrence (should not be included)
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => $todayStart->copy()->addHours(14),
            'end_at_utc' => $todayStart->copy()->addHours(16),
            'status' => 'cancelled',
        ]);

        $todayEvents = $this->eventService->getEventsToday(10);

        $this->assertCount(1, $todayEvents, 'Should only include scheduled occurrences');
    }

    public function test_getUpcomingEventsForHomepage_returns_events_in_date_range(): void
    {
        $now = now()->utc()->startOfDay();
        $inRange = $now->copy()->addDays(15); // Within 30 days
        $outOfRange = $now->copy()->addDays(35); // Beyond 30 days
        $past = $now->copy()->subDay(); // In the past

        // Event within range (should be included)
        $this->createTestEvent([
            'start_at_utc' => $inRange->copy()->addHours(10),
            'end_at_utc' => $inRange->copy()->addHours(12),
        ]);

        // Event out of range (should not be included)
        $this->createTestEvent([
            'start_at_utc' => $outOfRange->copy()->addHours(10),
            'end_at_utc' => $outOfRange->copy()->addHours(12),
        ]);

        // Past event (should not be included)
        $this->createTestEvent([
            'start_at_utc' => $past->copy()->addHours(10),
            'end_at_utc' => $past->copy()->addHours(12),
        ]);

        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10);

        $this->assertCount(1, $upcomingEvents, 'Should return only events within the 30-day range');

        // Verify the structure of returned events
        foreach ($upcomingEvents as $event) {
            $this->assertArrayHasKey('id', $event);
            $this->assertArrayHasKey('name', $event);
            $this->assertArrayHasKey('href', $event);
            $this->assertArrayHasKey('image_url', $event);
            $this->assertArrayHasKey('price_from', $event);
            $this->assertArrayHasKey('price_to', $event);
            $this->assertArrayHasKey('currency', $event);
            $this->assertArrayHasKey('date_short', $event);
            $this->assertArrayHasKey('category_name', $event);
        }
    }

    public function test_getUpcomingEventsForHomepage_respects_custom_date_range(): void
    {
        $customStart = now()->utc()->addDays(5);
        $customEnd = now()->utc()->addDays(10);

        // Event within custom range
        $this->createTestEvent([
            'start_at_utc' => $customStart->copy()->addDays(2),
            'end_at_utc' => $customStart->copy()->addDays(2)->addHours(2),
        ]);

        // Event outside custom range (but within default 30 days)
        $this->createTestEvent([
            'start_at_utc' => now()->utc()->addDays(15),
            'end_at_utc' => now()->utc()->addDays(15)->addHours(2),
        ]);

        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10, $customStart, $customEnd);

        $this->assertCount(1, $upcomingEvents, 'Should respect custom date range');
    }

    public function test_getUpcomingEventsForHomepage_respects_limit_parameter(): void
    {
        // Create 5 upcoming events
        for ($i = 1; $i <= 5; $i++) {
            $this->createTestEvent([
                'start_at_utc' => now()->utc()->addDays($i)->addHours(10),
                'end_at_utc' => now()->utc()->addDays($i)->addHours(12),
            ]);
        }

        $limitedEvents = $this->eventService->getUpcomingEventsForHomepage(3);

        $this->assertCount(3, $limitedEvents, 'Should respect the limit parameter');
    }

    public function test_getUpcomingEventsForHomepage_orders_by_earliest_occurrence(): void
    {
        $now = now()->utc();

        // Create events in reverse chronological order
        $event3 = $this->createTestEvent([
            'start_at_utc' => $now->copy()->addDays(15),
            'end_at_utc' => $now->copy()->addDays(15)->addHours(2),
        ]);

        $event1 = $this->createTestEvent([
            'start_at_utc' => $now->copy()->addDays(5),
            'end_at_utc' => $now->copy()->addDays(5)->addHours(2),
        ]);

        $event2 = $this->createTestEvent([
            'start_at_utc' => $now->copy()->addDays(10),
            'end_at_utc' => $now->copy()->addDays(10)->addHours(2),
        ]);

        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10);

        $this->assertCount(3, $upcomingEvents);

        // Verify events are ordered by earliest occurrence
        $this->assertEquals($event1->id, $upcomingEvents[0]['id'], 'First event should be the earliest');
        $this->assertEquals($event2->id, $upcomingEvents[1]['id'], 'Second event should be the middle one');
        $this->assertEquals($event3->id, $upcomingEvents[2]['id'], 'Third event should be the latest');
    }

    public function test_getUpcomingEventsForHomepage_returns_empty_array_when_no_upcoming_events(): void
    {
        // Create only past events
        $yesterday = now()->utc()->subDay()->startOfDay();

        $this->createTestEvent([
            'start_at_utc' => $yesterday->copy()->addHours(10),
            'end_at_utc' => $yesterday->copy()->addHours(12),
        ]);

        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10);

        $this->assertEmpty($upcomingEvents, 'Should return empty array when no upcoming events');
    }

    public function test_timezone_consistency_across_methods(): void
    {
        // Test that both methods handle timezone consistently
        $todayUtc = now()->utc()->startOfDay()->addHours(10);

        $this->createTestEvent([
            'start_at_utc' => $todayUtc,
            'end_at_utc' => $todayUtc->copy()->addHours(2),
        ]);

        $todayEvents = $this->eventService->getEventsToday(10);
        $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10);

        // If the event is happening today, it should appear in both results
        // (since today is within the upcoming 30-day range)
        $this->assertCount(1, $todayEvents, 'Event should appear in today events');
        $this->assertCount(1, $upcomingEvents, 'Event should also appear in upcoming events');
    }
}
