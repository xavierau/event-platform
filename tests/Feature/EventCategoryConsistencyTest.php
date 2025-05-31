<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Event;
use App\Models\Category;
use App\Models\EventOccurrence;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class EventCategoryConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('app.locale', 'zh-TW');
        Config::set('app.fallback_locale', 'zh-TW');
        Config::set('app.available_locales', [
            'en' => 'English',
            'zh-TW' => 'Traditional Chinese',
            'zh-CN' => 'Simplified Chinese',
        ]);
    }

    private function createTestEvent(array $eventData = [], array $occurrenceData = []): Event
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $defaultEventData = [
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event'],
            'description' => ['en' => 'Test Description'],
            'event_status' => 'published',
            'visibility' => 'public',
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

    public function test_production_scenario_magic_category_events(): void
    {
        // Recreate the production scenario: 魔術 category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => 'Magic', 'zh-TW' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create an event that should appear in More Events (beyond 30 days)
        $moreEventsEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => '魔術夢幻學院'],
        ], [
            'start_at_utc' => now()->utc()->addDays(35), // Beyond 30-day window
            'end_at_utc' => now()->utc()->addDays(35)->addHours(2),
        ]);

        // Create an event that should appear in Upcoming Events (within 30 days)
        $upcomingEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Magic Show Soon'],
        ], [
            'start_at_utc' => now()->utc()->addDays(15), // Within 30-day window
            'end_at_utc' => now()->utc()->addDays(15)->addHours(2),
        ]);

        // Test 1: Homepage should show both events in their respective sections
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);

        $homeProps = $homeResponse->getOriginalContent()->getData()['page']['props'];

        // Check More Events section
        $moreEvents = collect($homeProps['moreEvents'] ?? []);
        $moreEventIds = $moreEvents->pluck('id')->toArray();
        $this->assertContains($moreEventsEvent->id, $moreEventIds, 'Event should appear in More Events section');

        // Check Upcoming Events section
        $upcomingEvents = collect($homeProps['upcomingEvents'] ?? []);
        $upcomingEventIds = $upcomingEvents->pluck('id')->toArray();
        $this->assertContains($upcomingEvent->id, $upcomingEventIds, 'Event should appear in Upcoming Events section');

        // Test 2: Magic category page should show BOTH events
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($moreEventsEvent, $upcomingEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 2); // Should show both events

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($moreEventsEvent->id, $eventIds, 'More Events event should appear in category page');
            $this->assertContains($upcomingEvent->id, $eventIds, 'Upcoming event should appear in category page');

            // Verify category names are correct
            foreach ($events as $event) {
                $this->assertEquals('魔術', $event['category_name']);
            }

            return $page;
        });

        // Test 3: All Events page should show BOTH events
        $allEventsResponse = $this->get('/events');
        $allEventsResponse->assertStatus(200);

        $allEventsResponse->assertInertia(function ($page) use ($moreEventsEvent, $upcomingEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '全部活動')
                ->has('events', 2); // Should show both events

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($moreEventsEvent->id, $eventIds);
            $this->assertContains($upcomingEvent->id, $eventIds);

            return $page;
        });
    }

    public function test_events_with_different_time_ranges(): void
    {
        $category = Category::factory()->create([
            'name' => ['en' => 'Test Category', 'zh-TW' => '測試分類'],
            'slug' => 'test-category',
        ]);

        // Create events at different time ranges
        $todayEvent = $this->createTestEvent([
            'category_id' => $category->id,
            'name' => ['en' => 'Today Event'],
        ], [
            'start_at_utc' => now()->utc()->addHours(2),
            'end_at_utc' => now()->utc()->addHours(4),
        ]);

        $tomorrowEvent = $this->createTestEvent([
            'category_id' => $category->id,
            'name' => ['en' => 'Tomorrow Event'],
        ], [
            'start_at_utc' => now()->utc()->addDays(1),
            'end_at_utc' => now()->utc()->addDays(1)->addHours(2),
        ]);

        $nextWeekEvent = $this->createTestEvent([
            'category_id' => $category->id,
            'name' => ['en' => 'Next Week Event'],
        ], [
            'start_at_utc' => now()->utc()->addDays(7),
            'end_at_utc' => now()->utc()->addDays(7)->addHours(2),
        ]);

        $nextMonthEvent = $this->createTestEvent([
            'category_id' => $category->id,
            'name' => ['en' => 'Next Month Event'],
        ], [
            'start_at_utc' => now()->utc()->addDays(35),
            'end_at_utc' => now()->utc()->addDays(35)->addHours(2),
        ]);

        // All events should appear in the category page regardless of their timing
        $categoryResponse = $this->get('/events?category=test-category');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($todayEvent, $tomorrowEvent, $nextWeekEvent, $nextMonthEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '測試分類')
                ->has('events', 4); // Should show all 4 events

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($todayEvent->id, $eventIds);
            $this->assertContains($tomorrowEvent->id, $eventIds);
            $this->assertContains($nextWeekEvent->id, $eventIds);
            $this->assertContains($nextMonthEvent->id, $eventIds);

            return $page;
        });
    }

    public function test_event_date_range_formatting(): void
    {
        $category = Category::factory()->create([
            'name' => ['en' => 'Test Category'],
            'slug' => 'test-category',
        ]);

        // Create an event with a specific date to test formatting
        $event = $this->createTestEvent([
            'category_id' => $category->id,
            'name' => ['en' => 'Date Format Test Event'],
        ], [
            'start_at_utc' => now()->utc()->setDate(2025, 7, 6)->setTime(14, 30),
            'end_at_utc' => now()->utc()->setDate(2025, 7, 6)->setTime(16, 30),
        ]);

        $categoryResponse = $this->get('/events?category=test-category');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) {
            $page->component('Public/EventsByCategory')
                ->has('events', 1);

            $event = $page->toArray()['props']['events'][0];

            // Verify the date_range is properly formatted
            $this->assertNotNull($event['date_range']);
            $this->assertStringContainsString('2025.07.06', $event['date_range']);

            return $page;
        });
    }
}
