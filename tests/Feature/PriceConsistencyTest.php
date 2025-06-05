<?php

use Tests\TestCase;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Category;
use App\Models\Venue;
use App\Services\EventService;
use App\Services\PublicEventDisplayService;
use Carbon\Carbon;

class PriceConsistencyTest extends TestCase
{
    private EventService $eventService;
    private PublicEventDisplayService $publicEventDisplayService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventService = app(EventService::class);
        $this->publicEventDisplayService = app(PublicEventDisplayService::class);
    }

    /** @test */
    public function it_shows_consistent_pricing_between_homepage_and_detail_views()
    {
        // Create test data
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'event_status' => 'published',
            'category_id' => $category->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(5)->utc(),
            'status' => 'active',
        ]);

        // Create tickets with different availability windows
        // Ticket 1: Available now (should show in both views)
        $availableTicket = TicketDefinition::factory()->create([
            'price' => 48500, // HK$485.00
            'currency' => 'HKD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        // Ticket 2: Not available yet (should NOT show in either view after fix)
        $futureTicket = TicketDefinition::factory()->create([
            'price' => 482000, // HK$4,820.00
            'currency' => 'HKD',
            'availability_window_start_utc' => now()->addDays(20)->utc(),
            'availability_window_end_utc' => now()->addDays(30)->utc(),
        ]);

        // Attach tickets to occurrence
        $eventOccurrence->ticketDefinitions()->attach([
            $availableTicket->id => ['price_override' => null],
            $futureTicket->id => ['price_override' => null],
        ]);

        // Get pricing data from both services
        $homepageEvents = $this->eventService->getUpcomingEventsForHomepage(10);
        $homepageEvent = collect($homepageEvents)->firstWhere('id', $event->id);

        $detailData = $this->publicEventDisplayService->getEventDetailData($event->id);

        // Both views should now show only available tickets (HK$485.00)
        $this->assertEquals(
            $detailData['price_range'],
            $this->formatHomepagePriceRange($homepageEvent),
            'Price range should be consistent between homepage and detail views'
        );

        // Verify both show only the available ticket price
        $this->assertEquals('HK$485.00', $detailData['price_range']);
        $this->assertEquals('HK$485.00', $this->formatHomepagePriceRange($homepageEvent));
    }

    /** @test */
    public function it_shows_null_when_only_unavailable_tickets_exist()
    {
        // Create event with only unavailable tickets
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'event_status' => 'published',
            'category_id' => $category->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(5)->utc(),
            'status' => 'active',
        ]);

        // Create ticket that's not available yet
        $futureTicket = TicketDefinition::factory()->create([
            'price' => 50000, // HK$500.00
            'currency' => 'HKD',
            'availability_window_start_utc' => now()->addDays(20)->utc(),
            'availability_window_end_utc' => now()->addDays(30)->utc(),
        ]);

        $eventOccurrence->ticketDefinitions()->attach([
            $futureTicket->id => ['price_override' => null],
        ]);

        // Get pricing data from both services
        $homepageEvents = $this->eventService->getUpcomingEventsForHomepage(10);
        $homepageEvent = collect($homepageEvents)->firstWhere('id', $event->id);

        $detailData = $this->publicEventDisplayService->getEventDetailData($event->id);

        // Both should now consistently show null/no pricing when no tickets are available
        $this->assertNull($detailData['price_range']);
        $this->assertNull($this->formatHomepagePriceRange($homepageEvent));
    }

    /**
     * Helper to format homepage price data to match detail format
     */
    private function formatHomepagePriceRange(array $event): ?string
    {
        if (!isset($event['price_from']) || $event['price_from'] === null) {
            return null;
        }

        $currency = $event['currency'] ?? 'HKD';
        $symbol = config('currency.symbols.' . $currency, $currency);
        $priceFrom = $event['price_from'];
        $priceTo = $event['price_to'] ?? $priceFrom;

        if ($priceFrom == $priceTo) {
            return $symbol . number_format($priceFrom, 2);
        }

        return $symbol . number_format($priceFrom, 2) . '-' . number_format($priceTo, 2);
    }
}
