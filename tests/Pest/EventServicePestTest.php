<?php

use App\Services\EventService;
use App\Actions\Event\UpsertEventAction;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\Category;
use App\Models\Venue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->eventService = new EventService(new UpsertEventAction());
});

it('can get events happening today with modern Pest syntax', function () {
    // Create a test event for today
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Today Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->startOfDay()->addHours(10),
        'end_at_utc' => now()->utc()->startOfDay()->addHours(12),
        'status' => 'scheduled',
    ]);

    $todayEvents = $this->eventService->getEventsToday(10);

    expect($todayEvents)
        ->toHaveCount(1)
        ->and($todayEvents[0])
        ->toHaveKeys(['id', 'name', 'href', 'image_url', 'price_from', 'price_to', 'currency', 'start_time', 'venue_name', 'category_name'])
        ->and($todayEvents[0]['name'])
        ->toBe(['en' => 'Today Event']);
});

it('returns empty array when no events today', function () {
    // Create an event for tomorrow
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Tomorrow Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addDay()->startOfDay()->addHours(10),
        'end_at_utc' => now()->utc()->addDay()->startOfDay()->addHours(12),
        'status' => 'scheduled',
    ]);

    $todayEvents = $this->eventService->getEventsToday(10);

    expect($todayEvents)->toBeEmpty();
});

test('upcoming events are ordered by earliest occurrence', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create events in reverse chronological order
    $laterEvent = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Later Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    $earlierEvent = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Earlier Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    EventOccurrence::factory()->create([
        'event_id' => $laterEvent->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addDays(10),
        'end_at_utc' => now()->utc()->addDays(10)->addHours(2),
        'status' => 'scheduled',
    ]);

    EventOccurrence::factory()->create([
        'event_id' => $earlierEvent->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addDays(5),
        'end_at_utc' => now()->utc()->addDays(5)->addHours(2),
        'status' => 'scheduled',
    ]);

    $upcomingEvents = $this->eventService->getUpcomingEventsForHomepage(10);

    expect($upcomingEvents)
        ->toHaveCount(2)
        ->and($upcomingEvents[0]['id'])
        ->toBe($earlierEvent->id)
        ->and($upcomingEvents[1]['id'])
        ->toBe($laterEvent->id);
});
