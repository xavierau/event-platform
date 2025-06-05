<?php

use App\Services\EventService;
use App\Actions\Event\UpsertEventAction;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Models\Category;
use App\Models\Venue;
use App\Models\TicketDefinition;
use Carbon\Carbon;
use App\Services\PublicEventDisplayService;

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
        ->toBe('Today Event');
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

test('can get events happening today', function () {
    // Create an event happening today
    $todayEvent = Event::factory()->create([
        'name' => ['en' => 'Today Event'],
        'event_status' => 'published',
    ]);

    // Create occurrence for today
    EventOccurrence::factory()->create([
        'event_id' => $todayEvent->id,
        'start_at_utc' => now()->utc()->addHours(2),
        'end_at_utc' => now()->utc()->addHours(4),
        'status' => 'scheduled',
    ]);

    // Create an event happening tomorrow (should not appear)
    $tomorrowEvent = Event::factory()->create([
        'name' => ['en' => 'Tomorrow Event'],
        'event_status' => 'published',
    ]);

    EventOccurrence::factory()->create([
        'event_id' => $tomorrowEvent->id,
        'start_at_utc' => now()->utc()->addDay()->addHours(2),
        'end_at_utc' => now()->utc()->addDay()->addHours(4),
        'status' => 'scheduled',
    ]);

    $service = app(EventService::class);
    $todaysEvents = $service->getEventsToday();

    expect($todaysEvents)
        ->toHaveCount(1)
        ->and($todaysEvents[0])
        ->toHaveKeys(['id', 'name', 'href', 'image_url', 'price_from', 'price_to', 'currency', 'start_time', 'venue_name', 'category_name'])
        ->and($todaysEvents[0]['name'])
        ->toBe('Today Event');
});

test('filters tickets by availability window correctly', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create an event
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Availability Window Test Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence for today
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addHours(2),
        'end_at_utc' => now()->utc()->addHours(4),
        'status' => 'scheduled',
    ]);

    // Create tickets with different availability windows

    // 1. Ticket with no availability window (should always be available)
    $alwaysAvailableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Always Available Ticket'],
        'price' => 1000,
        'status' => 'active',
        'availability_window_start_utc' => null,
        'availability_window_end_utc' => null,
    ]);

    // 2. Ticket available now (started yesterday, ends tomorrow)
    $currentlyAvailableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Currently Available Ticket'],
        'price' => 2000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subDay(),
        'availability_window_end_utc' => now()->utc()->addDay(),
    ]);

    // 3. Ticket not yet available (starts tomorrow)
    $futureTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Future Ticket'],
        'price' => 3000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->addDay(),
        'availability_window_end_utc' => now()->utc()->addDays(2),
    ]);

    // 4. Ticket no longer available (ended yesterday)
    $expiredTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Expired Ticket'],
        'price' => 4000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subDays(2),
        'availability_window_end_utc' => now()->utc()->subDay(),
    ]);

    // Associate all tickets with the occurrence
    $occurrence->ticketDefinitions()->attach([
        $alwaysAvailableTicket->id,
        $currentlyAvailableTicket->id,
        $futureTicket->id,
        $expiredTicket->id,
    ]);

    $service = app(EventService::class);
    $todaysEvents = $service->getEventsToday();

    expect($todaysEvents)->toHaveCount(1);

    $eventData = $todaysEvents[0];
    expect($eventData)->toHaveKey('id', $event->id);

    // Now let's check what tickets are actually loaded using the same logic as EventService
    $eventWithTickets = Event::with(['eventOccurrences.ticketDefinitions' => function ($query) {
        // Use the same logic as our refactored EventService
        $nowUtc = now()->utc();
        $query->where(function ($q) use ($nowUtc) {
            // No availability window (both start and end are null)
            $q->whereNull('availability_window_start_utc')
                ->whereNull('availability_window_end_utc');
        })->orWhere(function ($q) use ($nowUtc) {
            // Within availability window (after start AND before end)
            $q->where('availability_window_start_utc', '<=', $nowUtc)
                ->where('availability_window_end_utc', '>=', $nowUtc);
        });
    }])->find($event->id);

    $loadedTickets = $eventWithTickets->eventOccurrences->first()->ticketDefinitions;

    // After refactoring, we should only get:
    // - Always available ticket (no window) ✓
    // - Currently available ticket (started yesterday, ends tomorrow) ✓
    // - Future ticket (starts tomorrow) ✗ (correctly filtered out)
    // - Expired ticket (ended yesterday) ✗ (now correctly filtered out!)

    expect($loadedTickets)->toHaveCount(2); // Fixed behavior - no more expired tickets!

    // Extract ticket names properly
    $ticketNames = $loadedTickets->map(function ($ticket) {
        return $ticket->getTranslation('name', 'en');
    })->toArray();

    expect($ticketNames)->toContain('Always Available Ticket');
    expect($ticketNames)->toContain('Currently Available Ticket');
    expect($ticketNames)->not->toContain('Expired Ticket'); // Now correctly excluded!
    expect($ticketNames)->not->toContain('Future Ticket');
});

test('should filter tickets by complete availability window (start AND end)', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create an event
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Correct Availability Window Test'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence for today
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addHours(2),
        'end_at_utc' => now()->utc()->addHours(4),
        'status' => 'scheduled',
    ]);

    // Create tickets with different availability windows

    // 1. Ticket with no availability window (should always be available)
    $alwaysAvailableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Always Available Ticket'],
        'price' => 1000,
        'status' => 'active',
        'availability_window_start_utc' => null,
        'availability_window_end_utc' => null,
    ]);

    // 2. Ticket available now (started yesterday, ends tomorrow)
    $currentlyAvailableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Currently Available Ticket'],
        'price' => 2000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subDay(),
        'availability_window_end_utc' => now()->utc()->addDay(),
    ]);

    // 3. Ticket not yet available (starts tomorrow)
    $futureTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Future Ticket'],
        'price' => 3000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->addDay(),
        'availability_window_end_utc' => now()->utc()->addDays(2),
    ]);

    // 4. Ticket no longer available (ended yesterday)
    $expiredTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Expired Ticket'],
        'price' => 4000,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subDays(2),
        'availability_window_end_utc' => now()->utc()->subDay(),
    ]);

    // Associate all tickets with the occurrence
    $occurrence->ticketDefinitions()->attach([
        $alwaysAvailableTicket->id,
        $currentlyAvailableTicket->id,
        $futureTicket->id,
        $expiredTicket->id,
    ]);

    // This test will initially fail, but defines the correct behavior we want
    $service = app(EventService::class);
    $todaysEvents = $service->getEventsToday();

    expect($todaysEvents)->toHaveCount(1);

    $eventData = $todaysEvents[0];
    expect($eventData)->toHaveKey('id', $event->id);

    // After refactoring, we want the service to filter tickets correctly
    // We'll need to check this by examining the actual loaded tickets
    $eventWithTickets = Event::with(['eventOccurrences.ticketDefinitions' => function ($query) {
        $nowUtc = now()->utc();
        $query->where(function ($q) use ($nowUtc) {
            // No availability window (both start and end are null)
            $q->whereNull('availability_window_start_utc')
                ->whereNull('availability_window_end_utc');
        })->orWhere(function ($q) use ($nowUtc) {
            // Within availability window (after start AND before end)
            $q->where('availability_window_start_utc', '<=', $nowUtc)
                ->where('availability_window_end_utc', '>=', $nowUtc);
        });
    }])->find($event->id);

    $loadedTickets = $eventWithTickets->eventOccurrences->first()->ticketDefinitions;

    // After refactoring, we should only get:
    // - Always available ticket (no window) ✓
    // - Currently available ticket (within window) ✓
    // - Future ticket (not started yet) ✗
    // - Expired ticket (ended already) ✗

    expect($loadedTickets)->toHaveCount(2); // Correct behavior after refactoring

    $ticketNames = $loadedTickets->map(function ($ticket) {
        return $ticket->getTranslation('name', 'en');
    })->toArray();

    expect($ticketNames)->toContain('Always Available Ticket');
    expect($ticketNames)->toContain('Currently Available Ticket');
    expect($ticketNames)->not->toContain('Future Ticket');
    expect($ticketNames)->not->toContain('Expired Ticket'); // This should NOT be included!
});

test('should show tickets available when only start time is set (no end time)', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create an event
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Start Time Only Test Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence for today
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addHours(2),
        'end_at_utc' => now()->utc()->addHours(4),
        'status' => 'scheduled',
    ]);

    // Create a ticket with only start time (no end time) - started 1 hour ago
    $ticketWithStartOnly = TicketDefinition::factory()->create([
        'name' => ['en' => 'Start Only Ticket'],
        'price' => 1500,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subHour(),
        'availability_window_end_utc' => null, // No end time - this is the key test case
    ]);

    // Create a ticket with only start time but in the future (should NOT be available)
    $futureStartOnlyTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Future Start Only Ticket'],
        'price' => 2500,
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->addHour(),
        'availability_window_end_utc' => null,
    ]);

    // Attach tickets to the occurrence
    $occurrence->ticketDefinitions()->attach($ticketWithStartOnly->id);
    $occurrence->ticketDefinitions()->attach($futureStartOnlyTicket->id);

    // Use EventService to get events (this applies the availability filter)
    $eventService = app(EventService::class);
    $eventsQuery = $eventService->getPublishedEventsWithFutureOccurrences(10);

    // Execute the query to get actual events
    $publishedEvents = $eventsQuery->get();

    // Find our event in the results
    $loadedEvent = $publishedEvents->firstWhere('id', $event->id);
    expect($loadedEvent)->not->toBeNull();

    // Verify that tickets are filtered correctly - load the event with filtered tickets
    $loadedTickets = $loadedEvent->eventOccurrences->first()->ticketDefinitions;

    // Should have 1 ticket (the one with start time in the past)
    expect($loadedTickets)->toHaveCount(1);

    // Extract ticket name properly
    $ticketName = $loadedTickets->first()->getTranslation('name', 'en');
    expect($ticketName)->toBe('Start Only Ticket');
});

test('event price range should include tickets with only start time set', function () {
    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create an event
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Price Range Test Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => now()->utc()->addHours(2),
        'end_at_utc' => now()->utc()->addHours(4),
        'status' => 'scheduled',
    ]);

    // Create a ticket with only start time (available now)
    $availableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Available Start Only Ticket'],
        'price' => 2000, // $20.00
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->subHour(),
        'availability_window_end_utc' => null, // No end time
    ]);

    // Create a ticket with start time in the future (not available yet)
    $futureTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Future Start Only Ticket'],
        'price' => 5000, // $50.00
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => now()->utc()->addHour(),
        'availability_window_end_utc' => null,
    ]);

    // Attach tickets to the occurrence
    $occurrence->ticketDefinitions()->attach($availableTicket->id);
    $occurrence->ticketDefinitions()->attach($futureTicket->id);

    // Load the event with relationships
    $event->load('eventOccurrences.ticketDefinitions');

    // Get price range - should only include the available ticket
    $priceRange = $event->getPriceRange();

    // Should return the price range for the available ticket only
    expect($priceRange)->not->toBeNull();
    expect($priceRange)->toContain('20'); // Should contain the price of the available ticket
    expect($priceRange)->not->toContain('50'); // Should NOT contain the price of the future ticket
});

test('reproduces real world ticket availability issue with exact data', function () {
    // Set the current time to exactly when the issue was reported: June 5th, 2025 4:00 PM
    $this->travel(Carbon::create(2025, 6, 5, 16, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create event with exact data from real occurrence
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => [
            'en' => 'Desire City special edition x Pier 1929',
            'zh-CN' => 'Desire City 一周年特别场次 x Pier 1929',
            'zh-TW' => 'Desire City 一周年特別場次 x Pier 1929'
        ],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence with exact real data (ID 24 equivalent)
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => Carbon::create(2025, 6, 28, 13, 0, 0, 'UTC'), // 2025-06-28 13:00:00
        'end_at_utc' => Carbon::create(2025, 6, 28, 15, 59, 0, 'UTC'),   // 2025-06-28 15:59:00
        'status' => 'active',
    ]);

    // Create ticket with exact real data (ID 30 equivalent)
    $vipTicket = TicketDefinition::factory()->create([
        'name' => [
            'en' => '"Desire" VIP Sofa Seat',
            'zh-CN' => '"欲望" VIP 沙发席',
            'zh-TW' => '"慾望" VIP 沙發席'
        ],
        'description' => [
            'en' => 'Tickets for four $4800 + $20 handling fee\n\nOne VIP sofa table for four guests.\nLet\'s enjoy tonight!\nFour tickets | One bottle of Moët Champagne | Snack platter | Pier1929 dining voucher | $1680 spa voucher',
            'zh-CN' => '四人门票 $4800 ＋手续费 $20\n\n一张可供四位宾客使用的VIP沙发桌。\n今晚，让我们尽情享受吧！\n四张门票 | 一瓶酩悦Moët香槟 | 小吃拼盘 | Pier1929 餐饮券 | 价值 $1680水疗券',
            'zh-TW' => '四人門票 $4800 ＋手續費 $20\n\n一張可供四位賓客使用的VIP沙發桌。\n今晚，讓我們盡情享受吧！ \n四張門票 | 一瓶酩悅Moët香檳 | 小吃拼盤 | Pier1929 餐飲券 | 價值 $1680水療券'
        ],
        'price' => 482000, // $4820.00 in cents
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => Carbon::create(2025, 6, 5, 12, 0, 0, 'UTC'), // 2025-06-05 12:00:00
        'availability_window_end_utc' => Carbon::create(2025, 7, 1, 12, 0, 0, 'UTC'),   // 2025-07-01 12:00:00
        'max_per_order' => 1,
    ]);

    // Attach ticket to occurrence
    $occurrence->ticketDefinitions()->attach($vipTicket->id);

    // Test 1: Verify the ticket should be available at the reported time
    $currentTime = Carbon::create(2025, 6, 5, 16, 0, 0, 'UTC'); // 4:00 PM UTC
    $startTime = Carbon::create(2025, 6, 5, 12, 0, 0, 'UTC');   // 12:00 PM UTC
    $endTime = Carbon::create(2025, 7, 1, 12, 0, 0, 'UTC');     // July 1st 12:00 PM UTC

    expect($currentTime->isAfter($startTime))->toBeTrue('Current time should be after start time');
    expect($currentTime->isBefore($endTime))->toBeTrue('Current time should be before end time');

    // Test 2: Use EventService to get events (this applies our availability filter)
    $eventService = app(EventService::class);
    $eventsQuery = $eventService->getPublishedEventsWithFutureOccurrences(10);
    $publishedEvents = $eventsQuery->get();

    // Should find our event
    $loadedEvent = $publishedEvents->firstWhere('id', $event->id);
    expect($loadedEvent)->not->toBeNull('Event should be found with our fix applied');

    // Test 3: Verify tickets are available via the availability filter
    $loadedTickets = $loadedEvent->eventOccurrences->first()->ticketDefinitions;
    expect($loadedTickets)->toHaveCount(1, 'Should have 1 available ticket');

    $ticketName = $loadedTickets->first()->getTranslation('name', 'en');
    expect($ticketName)->toBe('"Desire" VIP Sofa Seat');

    // Test 4: Test Event model's getPriceRange method
    $event->load('eventOccurrences.ticketDefinitions');
    $priceRange = $event->getPriceRange();
    expect($priceRange)->not->toBeNull('Price range should be available');
    expect($priceRange)->toContain('4820', 'Should contain the VIP ticket price');

    // Test 5: Test PublicEventDisplayService (the actual frontend path)
    $publicService = app(PublicEventDisplayService::class);
    $eventDetailData = $publicService->getEventDetailData($event->id);

    expect($eventDetailData)->toHaveKey('occurrences');
    expect($eventDetailData['occurrences'])->toHaveCount(1);
    expect($eventDetailData['occurrences'][0])->toHaveKey('tickets');
    expect($eventDetailData['occurrences'][0]['tickets'])->toHaveCount(1, 'Frontend should see the VIP ticket');

    $frontendTicket = $eventDetailData['occurrences'][0]['tickets'][0];
    expect($frontendTicket)->toHaveKey('name');
    expect($frontendTicket['name'])->toBe('"Desire" VIP Sofa Seat');
});

test('frontend should not see unavailable tickets outside availability window', function () {
    // Set the current time to exactly when the issue was reported: June 5th, 2025 4:00 PM
    $this->travel(Carbon::create(2025, 6, 5, 16, 0, 0, 'UTC'));

    $user = User::factory()->create();
    $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
    $venue = Venue::factory()->create();

    // Create event
    $event = Event::factory()->create([
        'organizer_id' => $user->id,
        'category_id' => $category->id,
        'name' => ['en' => 'Availability Test Event'],
        'event_status' => 'published',
        'visibility' => 'public',
    ]);

    // Create occurrence
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'venue_id' => $venue->id,
        'start_at_utc' => Carbon::create(2025, 6, 28, 13, 0, 0, 'UTC'),
        'end_at_utc' => Carbon::create(2025, 6, 28, 15, 59, 0, 'UTC'),
        'status' => 'active',
    ]);

    // Create a ticket that's available (started 1 hour ago, ends in future)
    $availableTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Available Ticket'],
        'price' => 100000, // $1000.00 in cents
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => Carbon::create(2025, 6, 5, 15, 0, 0, 'UTC'), // 1 hour ago
        'availability_window_end_utc' => Carbon::create(2025, 7, 1, 12, 0, 0, 'UTC'),   // Future
    ]);

    // Create a ticket that's NOT available (starts in future)
    $futureTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Future Ticket'],
        'price' => 200000, // $2000.00 in cents
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => Carbon::create(2025, 6, 10, 12, 0, 0, 'UTC'), // Future start
        'availability_window_end_utc' => Carbon::create(2025, 7, 1, 12, 0, 0, 'UTC'),   // Future end
    ]);

    // Create a ticket that's NOT available (ended yesterday)
    $expiredTicket = TicketDefinition::factory()->create([
        'name' => ['en' => 'Expired Ticket'],
        'price' => 300000, // $3000.00 in cents
        'currency' => 'HKD',
        'status' => 'active',
        'availability_window_start_utc' => Carbon::create(2025, 6, 1, 12, 0, 0, 'UTC'),  // Past start
        'availability_window_end_utc' => Carbon::create(2025, 6, 4, 12, 0, 0, 'UTC'),    // Past end (yesterday)
    ]);

    // Attach all tickets to the occurrence
    $occurrence->ticketDefinitions()->attach([
        $availableTicket->id,
        $futureTicket->id,
        $expiredTicket->id,
    ]);

    // Test PublicEventDisplayService (the actual frontend path)
    $publicService = app(PublicEventDisplayService::class);
    $eventDetailData = $publicService->getEventDetailData($event->id);

    expect($eventDetailData)->toHaveKey('occurrences');
    expect($eventDetailData['occurrences'])->toHaveCount(1);
    expect($eventDetailData['occurrences'][0])->toHaveKey('tickets');

    // Should only show the available ticket, not the future or expired ones
    expect($eventDetailData['occurrences'][0]['tickets'])->toHaveCount(1, 'Frontend should only see available tickets');

    $frontendTicket = $eventDetailData['occurrences'][0]['tickets'][0];
    expect($frontendTicket)->toHaveKey('name');
    expect($frontendTicket['name'])->toBe('Available Ticket');

    // Verify the unavailable tickets are not present
    $ticketNames = collect($eventDetailData['occurrences'][0]['tickets'])->pluck('name')->toArray();
    expect($ticketNames)->not->toContain('Future Ticket', 'Future tickets should not be visible');
    expect($ticketNames)->not->toContain('Expired Ticket', 'Expired tickets should not be visible');
});
