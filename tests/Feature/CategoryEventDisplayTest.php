<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Event;
use App\Models\Category;
use App\Models\EventOccurrence;
use App\Models\Venue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryEventDisplayTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_event_appears_in_both_more_events_and_category_page(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create an event in this category with occurrence beyond 30 days to appear in "More Events"
        $magicEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => '魔術夢幻學院'],
        ], [
            'start_at_utc' => now()->utc()->addDays(35), // Beyond 30-day upcoming window
            'end_at_utc' => now()->utc()->addDays(35)->addHours(2),
        ]);

        // Test 1: Check if event appears in "More Events" section on homepage
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);

        $homeProps = $homeResponse->getOriginalContent()->getData()['page']['props'];
        $moreEvents = collect($homeProps['moreEvents'] ?? []);

        $eventInMoreEvents = $moreEvents->firstWhere('id', $magicEvent->id);
        $this->assertNotNull($eventInMoreEvents, 'Event should appear in More Events section');
        $this->assertEquals('魔術', $eventInMoreEvents['category_name']);

        // Test 2: Check if event appears in its own category page
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($magicEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 1)
                ->where('events.0.id', $magicEvent->id)
                ->where('events.0.name', '魔術夢幻學院')
                ->where('events.0.category_name', '魔術');
        });
    }

    public function test_event_appears_in_all_events_page(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create an event in this category
        $magicEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => '魔術夢幻學院'],
        ], [
            'start_at_utc' => now()->utc()->addDays(5), // Within reasonable future range
            'end_at_utc' => now()->utc()->addDays(5)->addHours(2),
        ]);

        // Test: Check if event appears in "All Events" page
        $allEventsResponse = $this->get('/events');
        $allEventsResponse->assertStatus(200);

        $allEventsResponse->assertInertia(function ($page) use ($magicEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', 'All Events')
                ->has('events', 1)
                ->where('events.0.id', $magicEvent->id)
                ->where('events.0.name', '魔術夢幻學院')
                ->where('events.0.category_name', '魔術');
        });
    }

    public function test_multiple_events_in_same_category(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create multiple events in this category
        $event1 = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => '魔術夢幻學院'],
        ]);

        $event2 = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Magic Show 2'],
        ]);

        // Test: Check if both events appear in category page
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($event1, $event2) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 2);

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($event1->id, $eventIds);
            $this->assertContains($event2->id, $eventIds);

            return $page;
        });
    }

    public function test_event_with_past_occurrence_does_not_appear(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create an event with recent past occurrence (within 3 years - should appear)
        $pastEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Past Magic Event'],
        ], [
            'start_at_utc' => now()->utc()->subDays(1),
            'end_at_utc' => now()->utc()->subDays(1)->addHours(2),
        ]);

        // Create an event with future occurrence
        $futureEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Future Magic Event'],
        ]);

        // Test: Check category page shows both recent past and future events
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($futureEvent, $pastEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 2); // Should show both events

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($futureEvent->id, $eventIds, 'Future event should be included');
            $this->assertContains($pastEvent->id, $eventIds, 'Recent past event should be included');

            return $page;
        });
    }

    public function test_draft_event_does_not_appear_in_public_pages(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create a draft event
        $draftEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Draft Magic Event'],
            'event_status' => 'draft',
        ]);

        // Create a published event beyond 30 days to appear in "More Events"
        $publishedEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Published Magic Event'],
            'event_status' => 'published',
        ], [
            'start_at_utc' => now()->utc()->addDays(35), // Beyond 30-day upcoming window
            'end_at_utc' => now()->utc()->addDays(35)->addHours(2),
        ]);

        // Test: Check category page only shows published events
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($publishedEvent, $draftEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 1)
                ->where('events.0.id', $publishedEvent->id);

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertNotContains($draftEvent->id, $eventIds);

            return $page;
        });

        // Test: Check homepage More Events section only shows published events
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);

        $homeProps = $homeResponse->getOriginalContent()->getData()['page']['props'];
        $moreEvents = collect($homeProps['moreEvents'] ?? []);
        $moreEventIds = $moreEvents->pluck('id')->toArray();

        $this->assertContains($publishedEvent->id, $moreEventIds);
        $this->assertNotContains($draftEvent->id, $moreEventIds);
    }

    public function test_event_with_very_old_occurrence_does_not_appear(): void
    {
        // Create a specific category
        $magicCategory = Category::factory()->create([
            'name' => ['en' => '魔術'],
            'slug' => 'magic',
        ]);

        // Create an event with recent past occurrence (within 3 years - should appear)
        $recentPastEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Recent Past Magic Event'],
        ], [
            'start_at_utc' => now()->utc()->subDays(1),
            'end_at_utc' => now()->utc()->subDays(1)->addHours(2),
        ]);

        // Create an event with future occurrence
        $futureEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Future Magic Event'],
        ]);

        // Create an event with very old occurrence (beyond 3 years - should not appear)
        $veryOldEvent = $this->createTestEvent([
            'category_id' => $magicCategory->id,
            'name' => ['en' => 'Very Old Magic Event'],
        ], [
            'start_at_utc' => now()->utc()->subYears(4),
            'end_at_utc' => now()->utc()->subYears(4)->addHours(2),
        ]);

        // Test: Check category page shows recent past and future events, but not very old events
        $categoryResponse = $this->get('/events?category=magic');
        $categoryResponse->assertStatus(200);

        $categoryResponse->assertInertia(function ($page) use ($futureEvent, $recentPastEvent, $veryOldEvent) {
            $page->component('Public/EventsByCategory')
                ->where('title', '魔術')
                ->has('events', 2); // Should show future and recent past events

            $events = collect($page->toArray()['props']['events']);
            $eventIds = $events->pluck('id')->toArray();

            $this->assertContains($futureEvent->id, $eventIds, 'Future event should be included');
            $this->assertContains($recentPastEvent->id, $eventIds, 'Recent past event should be included');
            $this->assertNotContains($veryOldEvent->id, $eventIds, 'Very old event should not be included');

            return $page;
        });
    }
}
