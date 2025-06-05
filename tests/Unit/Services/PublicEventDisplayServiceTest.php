<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Venue;
use App\Services\CategoryService;
use App\Services\EventService;
use App\Services\PublicEventDisplayService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventDisplayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PublicEventDisplayService $service;
    protected EventService $eventService;
    protected CategoryService $categoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventService = app(EventService::class);
        $this->categoryService = app(CategoryService::class);
        $this->service = new PublicEventDisplayService($this->eventService, $this->categoryService);

        Carbon::setTestNow('2024-01-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function getEventsForListing_returns_all_events_when_no_category()
    {
        // Arrange
        $category = Category::factory()->create(['name' => ['en' => 'Music']]);
        $venue = Venue::factory()->create(['name' => ['en' => 'Test Venue']]);

        $event = Event::factory()->create([
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'category_id' => $category->id
        ]);

        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(5),
            'status' => 'scheduled'
        ]);

        // Act
        $result = $this->service->getEventsForListing();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('poster_url', $result);
        $this->assertArrayHasKey('events', $result);
        $this->assertCount(1, $result['events']);
        $this->assertEquals('Test Event', $result['events'][0]['name']);
    }

    /** @test */
    public function getEventsForListing_filters_by_category_slug()
    {
        // Arrange
        $musicCategory = Category::factory()->create([
            'name' => ['en' => 'Music'],
            'slug' => 'music'
        ]);
        $artCategory = Category::factory()->create([
            'name' => ['en' => 'Art'],
            'slug' => 'art'
        ]);
        $venue = Venue::factory()->create();

        $musicEvent = Event::factory()->create([
            'name' => ['en' => 'Music Event'],
            'event_status' => 'published',
            'category_id' => $musicCategory->id
        ]);

        $artEvent = Event::factory()->create([
            'name' => ['en' => 'Art Event'],
            'event_status' => 'published',
            'category_id' => $artCategory->id
        ]);

        foreach ([$musicEvent, $artEvent] as $event) {
            EventOccurrence::factory()->create([
                'event_id' => $event->id,
                'venue_id' => $venue->id,
                'start_at_utc' => now()->addDays(5),
                'status' => 'scheduled'
            ]);
        }

        // Act
        $result = $this->service->getEventsForListing('music');

        // Assert
        $this->assertEquals('Music', $result['title']);
        $this->assertCount(1, $result['events']);
        $this->assertEquals('Music Event', $result['events'][0]['name']);
    }

    /** @test */
    public function mapEventForListing_returns_correct_structure()
    {
        // Arrange
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]);
        $venue = Venue::factory()->create(['name' => ['en' => 'Test Venue']]);

        $event = Event::factory()->create([
            'name' => ['en' => 'Test Event'],
            'event_status' => 'published',
            'category_id' => $category->id
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(5),
            'status' => 'scheduled'
        ]);

        $ticket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'HKD'
        ]);

        $occurrence->ticketDefinitions()->attach($ticket->id);

        // Reload with relationships
        $event->load(['eventOccurrences.venue', 'eventOccurrences.ticketDefinitions', 'category']);

        // Act
        $result = $this->service->mapEventForListing($event);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('href', $result);
        $this->assertArrayHasKey('image_url', $result);
        $this->assertArrayHasKey('price_from', $result);
        $this->assertArrayHasKey('date_range', $result);
        $this->assertArrayHasKey('venue_name', $result);
        $this->assertArrayHasKey('category_name', $result);

        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals('Test Event', $result['name']);
        $this->assertEquals('Test Venue', $result['venue_name']);
        $this->assertEquals('Test Category', $result['category_name']);
    }

    /** @test */
    public function getEventDetailData_returns_complete_event_structure()
    {
        // Arrange
        $category = Category::factory()->create(['name' => ['en' => 'Music']]);
        $venue = Venue::factory()->create([
            'name' => ['en' => 'Concert Hall'],
            'address_line_1' => ['en' => '123 Music Street']
        ]);

        $event = Event::factory()->create([
            'name' => ['en' => 'Concert Event'],
            'description' => ['en' => 'A great concert'],
            'event_status' => 'published',
            'category_id' => $category->id
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Evening Show'],
            'start_at_utc' => '2024-02-15 19:00:00',
            'status' => 'scheduled',
            'timezone' => 'Asia/Hong_Kong'
        ]);

        $ticket = TicketDefinition::factory()->create([
            'name' => ['en' => 'VIP Ticket'],
            'description' => ['en' => 'Premium seating'],
            'price' => 10000,
            'currency' => 'HKD',
            'max_per_order' => 4,
            'min_per_order' => 1
        ]);

        $occurrence->ticketDefinitions()->attach($ticket->id);

        // Act
        $result = $this->service->getEventDetailData($event->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('category_tag', $result);
        $this->assertArrayHasKey('description_html', $result);
        $this->assertArrayHasKey('venue_name', $result);
        $this->assertArrayHasKey('occurrences', $result);

        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals('Concert Event', $result['name']);
        $this->assertEquals('Music', $result['category_tag']);
        $this->assertCount(1, $result['occurrences']);

        $occurrence = $result['occurrences'][0];
        $this->assertEquals('Evening Show', $occurrence['name']);
        $this->assertEquals('scheduled', $occurrence['status_tag']);
        $this->assertCount(1, $occurrence['tickets']);

        $ticket = $occurrence['tickets'][0];
        $this->assertEquals('VIP Ticket', $ticket['name']);
        $this->assertEquals('Premium seating', $ticket['description']);
        $this->assertEquals('HKD', $ticket['currency']);
    }

    /** @test */
    public function formatDateRange_handles_single_date()
    {
        // Arrange
        $date = Carbon::parse('2024-02-15 10:00:00');

        // Act
        $result = $this->service->formatDateRange($date, $date, 1);

        // Assert
        $this->assertEquals('2024.02.15', $result);
    }

    /** @test */
    public function formatDateRange_handles_same_month_range()
    {
        // Arrange
        $startDate = Carbon::parse('2024-02-15 10:00:00');
        $endDate = Carbon::parse('2024-02-20 10:00:00');

        // Act
        $result = $this->service->formatDateRange($startDate, $endDate, 2);

        // Assert
        $this->assertEquals('2024.02.15-20', $result);
    }

    /** @test */
    public function formatDateRange_handles_different_month_range()
    {
        // Arrange
        $startDate = Carbon::parse('2024-02-15 10:00:00');
        $endDate = Carbon::parse('2024-03-20 10:00:00');

        // Act
        $result = $this->service->formatDateRange($startDate, $endDate, 2);

        // Assert
        $this->assertEquals('2024.02.15-2024.03.20', $result);
    }

    /** @test */
    public function formatDateRange_returns_null_for_null_start_date()
    {
        // Act
        $result = $this->service->formatDateRange(null, null, 1);

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function carbonSafeParse_handles_carbon_instance()
    {
        // Arrange
        $carbon = Carbon::now();
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('carbonSafeParse');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, $carbon);

        // Assert
        $this->assertSame($carbon, $result);
    }

    /** @test */
    public function carbonSafeParse_handles_string_date()
    {
        // Arrange
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('carbonSafeParse');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, '2024-02-15 10:00:00');

        // Assert
        $this->assertInstanceOf(Carbon::class, $result);
        $this->assertEquals('2024-02-15 10:00:00', $result->format('Y-m-d H:i:s'));
    }

    /** @test */
    public function carbonSafeParse_handles_invalid_date()
    {
        // Arrange
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('carbonSafeParse');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, 'invalid-date');

        // Assert
        $this->assertInstanceOf(Carbon::class, $result);
        // Should return current UTC time as fallback
        $this->assertTrue($result->isUtc());
    }

    /** @test */
    public function calculateMinimumPrice_returns_correct_value()
    {
        // Arrange
        $event = Event::factory()->create(['event_status' => 'published']);
        $venue = Venue::factory()->create();

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(5),
            'status' => 'scheduled'
        ]);

        $ticket1 = TicketDefinition::factory()->create(['price' => 5000]); // $50
        $ticket2 = TicketDefinition::factory()->create(['price' => 3000]); // $30

        $occurrence->ticketDefinitions()->attach([$ticket1->id, $ticket2->id]);

        $event->load(['eventOccurrences.ticketDefinitions']);

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calculateMinimumPrice');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, $event);

        // Assert
        $this->assertEquals(30, $result); // Should return the minimum price in currency units
    }

    /** @test */
    public function getEventVenueName_returns_occurrence_venue_when_available()
    {
        // Arrange
        $venue = Venue::factory()->create(['name' => ['en' => 'Occurrence Venue']]);

        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id
        ]);

        $occurrence->load('venue');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEventVenueName');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->service, $event, $occurrence);

        // Assert
        $this->assertEquals('Occurrence Venue', $result);
    }

    /** @test */
    public function getEventVenueName_falls_back_to_primary_venue()
    {
        // Arrange
        $primaryVenue = Venue::factory()->create(['name' => ['en' => 'Primary Venue']]);
        $event = Event::factory()->create();

        // Create an occurrence to establish primary venue
        EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $primaryVenue->id
        ]);

        // Load the relationships that getPrimaryVenue() needs
        $event->load('eventOccurrences.venue');

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getEventVenueName');
        $method->setAccessible(true);

        // Act - pass null as occurrence to test fallback to getPrimaryVenue()
        $result = $method->invoke($this->service, $event, null);

        // Assert
        $this->assertEquals('Primary Venue', $result);
    }

    /** @test */
    public function getEventDetailData_calculates_price_range_with_multiple_tickets_having_null_availability_window()
    {
        // Arrange
        $category = Category::factory()->create(['name' => ['en' => 'Concert']]);
        $venue = Venue::factory()->create([
            'name' => ['en' => 'Music Hall'],
            'address_line_1' => ['en' => '456 Concert Street']
        ]);

        $event = Event::factory()->create([
            'name' => ['en' => 'Rock Concert'],
            'description' => ['en' => 'Amazing rock concert'],
            'event_status' => 'published',
            'category_id' => $category->id
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Friday Night Show'],
            'start_at_utc' => '2024-02-16 20:00:00',
            'status' => 'scheduled',
            'timezone' => 'Asia/Hong_Kong'
        ]);

        // Create multiple ticket definitions with null availability_window
        $ticketGeneral = TicketDefinition::factory()->create([
            'name' => ['en' => 'General Admission'],
            'description' => ['en' => 'Standard seating'],
            'price' => 8000, // $80.00
            'currency' => 'HKD',
            'availability_window_start_utc' => null,
            'availability_window_end_utc' => null,
        ]);

        $ticketVip = TicketDefinition::factory()->create([
            'name' => ['en' => 'VIP Package'],
            'description' => ['en' => 'Premium experience'],
            'price' => 15000, // $150.00
            'currency' => 'HKD',
            'availability_window_start_utc' => null,
            'availability_window_end_utc' => null,
        ]);

        $ticketStudent = TicketDefinition::factory()->create([
            'name' => ['en' => 'Student Discount'],
            'description' => ['en' => 'Discounted price for students'],
            'price' => 5000, // $50.00
            'currency' => 'HKD',
            'availability_window_start_utc' => null,
            'availability_window_end_utc' => null,
        ]);

        // Attach all tickets to the occurrence
        $occurrence->ticketDefinitions()->attach([
            $ticketGeneral->id => [
                'quantity_for_occurrence' => 100,
                'price_override' => null,
            ],
            $ticketVip->id => [
                'quantity_for_occurrence' => 20,
                'price_override' => null,
            ],
            $ticketStudent->id => [
                'quantity_for_occurrence' => 50,
                'price_override' => 6000, // Override to $60.00
            ],
        ]);

        // Act
        $result = $this->service->getEventDetailData($event->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('price_range', $result);

        // Should calculate price range from $60.00 (student override) to $150.00 (VIP)
        // Since all tickets have null availability_window, they should all be considered available
        $this->assertEquals('HK$60.00-150.00', $result['price_range']);

        // Verify the event structure is correct
        $this->assertEquals($event->id, $result['id']);
        $this->assertEquals('Rock Concert', $result['name']);
        $this->assertEquals('Concert', $result['category_tag']);
        $this->assertEquals('Music Hall', $result['venue_name']);
        // Check that venue_address contains our specified address_line_1
        $this->assertStringContainsString('456 Concert Street', $result['venue_address']);

        // Check that all tickets are included in the occurrence data
        $this->assertCount(1, $result['occurrences']);
        $occurrence = $result['occurrences'][0];
        $this->assertEquals('Friday Night Show', $occurrence['name']);
        $this->assertCount(3, $occurrence['tickets']);

        // Verify ticket names are present
        $ticketNames = array_column($occurrence['tickets'], 'name');
        $this->assertContains('General Admission', $ticketNames);
        $this->assertContains('VIP Package', $ticketNames);
        $this->assertContains('Student Discount', $ticketNames);
    }
}
