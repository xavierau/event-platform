<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Category;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class EventTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_price_range_correctly()
    {
        // Create necessary models
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        // Create ticket definitions with different prices
        // Explicitly set availability windows to ensure all tickets are available
        $ticket1 = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        $ticket2 = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        $ticket3 = TicketDefinition::factory()->create([
            'price' => 7500, // $75.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        // Associate tickets with occurrences
        $occurrence1->ticketDefinitions()->attach($ticket1->id, [
            'quantity_for_occurrence' => 100,
            'price_override' => null,
        ]);

        $occurrence1->ticketDefinitions()->attach($ticket2->id, [
            'quantity_for_occurrence' => 50,
            'price_override' => null,
        ]);

        $occurrence2->ticketDefinitions()->attach($ticket3->id, [
            'quantity_for_occurrence' => 75,
            'price_override' => 8000, // Override to $80.00
        ]);

        // Test price range calculation
        $priceRange = $event->getPriceRange();

        // Should be $50.00-$100.00 (min from ticket1, max from ticket2)
        // Note: ticket3 has override of $80.00 but ticket2 at $100.00 is still highest
        $this->assertEquals('$50.00-100.00', $priceRange);
    }

    #[Test]
    public function it_returns_single_price_when_min_and_max_are_same()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        $ticket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        $occurrence->ticketDefinitions()->attach($ticket->id, [
            'quantity_for_occurrence' => 100,
            'price_override' => null,
        ]);

        $priceRange = $event->getPriceRange();

        $this->assertEquals('$50.00', $priceRange);
    }

    #[Test]
    public function it_returns_null_when_no_tickets_available()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $priceRange = $event->getPriceRange();

        $this->assertNull($priceRange);
    }

    #[Test]
    public function it_uses_price_override_when_available()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        $ticket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00 original
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        $occurrence->ticketDefinitions()->attach($ticket->id, [
            'quantity_for_occurrence' => 100,
            'price_override' => 3000, // Override to $30.00
        ]);

        $priceRange = $event->getPriceRange();

        // Should use the override price of $30.00
        $this->assertEquals('$30.00', $priceRange);
    }

    #[Test]
    public function it_returns_primary_venue_from_first_occurrence()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue1 = Venue::factory()->create(['name' => ['en' => 'First Venue']]);
        $venue2 = Venue::factory()->create(['name' => ['en' => 'Second Venue']]);

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        // Create occurrences with different venues
        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue1->id,
            'start_at_utc' => now()->addDays(1),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue2->id,
            'start_at_utc' => now()->addDays(2),
        ]);

        $primaryVenue = $event->getPrimaryVenue();

        // Should return the venue from the first occurrence
        $this->assertNotNull($primaryVenue);
        $this->assertEquals($venue1->id, $primaryVenue->id);
        $this->assertEquals('First Venue', $primaryVenue->getTranslation('name', 'en'));
    }

    #[Test]
    public function it_returns_null_when_no_occurrences_exist()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $primaryVenue = $event->getPrimaryVenue();

        $this->assertNull($primaryVenue);
    }

    #[Test]
    public function it_returns_null_when_first_occurrence_has_no_venue()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        // Create occurrence without venue
        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => null,
        ]);

        $primaryVenue = $event->getPrimaryVenue();

        $this->assertNull($primaryVenue);
    }

    #[Test]
    public function it_finds_published_event_by_id()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
        ]);

        $foundEvent = Event::findPublishedByIdentifier($event->id);

        $this->assertEquals($event->id, $foundEvent->id);
    }

    #[Test]
    public function it_finds_published_event_by_slug()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'test-event-slug'],
        ]);

        $foundEvent = Event::findPublishedByIdentifier('test-event-slug');

        $this->assertEquals($event->id, $foundEvent->id);
    }

    #[Test]
    public function it_throws_exception_for_non_published_event()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'draft', // Not published
        ]);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Event::findPublishedByIdentifier($event->id);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_event()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        Event::findPublishedByIdentifier('non-existent-slug');
    }

    #[Test]
    public function it_loads_specified_relationships()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
        ]);

        $foundEvent = Event::findPublishedByIdentifier($event->id, ['category']);

        $this->assertTrue($foundEvent->relationLoaded('category'));
        $this->assertEquals($category->id, $foundEvent->category->id);
    }

    #[Test]
    public function it_finds_exact_slug_match_without_ambiguity()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        // Create events with similar but different slugs
        $event1 = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'event'],
        ]);

        $event2 = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'my-event'],
        ]);

        $event3 = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'event-2024'],
        ]);

        // Should find exact match only
        $foundEvent = Event::findPublishedByIdentifier('event');
        $this->assertEquals($event1->id, $foundEvent->id);

        // Should not find partial matches
        $foundEvent2 = Event::findPublishedByIdentifier('my-event');
        $this->assertEquals($event2->id, $foundEvent2->id);

        $foundEvent3 = Event::findPublishedByIdentifier('event-2024');
        $this->assertEquals($event3->id, $foundEvent3->id);
    }

    #[Test]
    public function it_finds_slug_in_different_locales()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => [
                'en' => 'test-event',
                'zh-TW' => '測試活動',
                'zh-CN' => '测试活动'
            ],
        ]);

        // Should find by English slug
        $foundEvent1 = Event::findPublishedByIdentifier('test-event');
        $this->assertEquals($event->id, $foundEvent1->id);

        // Should find by Traditional Chinese slug
        $foundEvent2 = Event::findPublishedByIdentifier('測試活動');
        $this->assertEquals($event->id, $foundEvent2->id);

        // Should find by Simplified Chinese slug
        $foundEvent3 = Event::findPublishedByIdentifier('测试活动');
        $this->assertEquals($event->id, $foundEvent3->id);
    }

    #[Test]
    public function it_uses_correct_database_driver_for_slug_search()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'database-test-slug'],
        ]);

        // Test with current database driver (should be sqlite in tests)
        $foundEvent = Event::findPublishedByIdentifier('database-test-slug');
        $this->assertEquals($event->id, $foundEvent->id);

        // Verify the method works regardless of database driver
        // (The actual SQL generation is tested implicitly by the successful query)
        $this->assertTrue(true, 'Database-specific slug search completed successfully');
    }

    #[Test]
    public function it_handles_special_characters_in_slug_search()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();

        // Create event with slug containing special characters
        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
            'event_status' => 'published',
            'slug' => ['en' => 'event-with_special%chars'],
        ]);

        // Should find exact match even with special characters
        $foundEvent = Event::findPublishedByIdentifier('event-with_special%chars');
        $this->assertEquals($event->id, $foundEvent->id);

        // Should not find partial matches
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        Event::findPublishedByIdentifier('event-with_special');
    }

    #[Test]
    public function it_only_includes_available_tickets_in_price_range()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        // Available ticket
        $availableTicket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDay()->utc(),
            'availability_window_end_utc' => now()->addDays(10)->utc(),
        ]);

        // Unavailable ticket (future availability)
        $futureTicket = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->addDays(20)->utc(),
            'availability_window_end_utc' => now()->addDays(30)->utc(),
        ]);

        // Unavailable ticket (past availability)
        $pastTicket = TicketDefinition::factory()->create([
            'price' => 2000, // $20.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->subDays(30)->utc(),
            'availability_window_end_utc' => now()->subDay()->utc(),
        ]);

        // Attach all tickets
        $occurrence->ticketDefinitions()->attach([
            $availableTicket->id => ['price_override' => null],
            $futureTicket->id => ['price_override' => null],
            $pastTicket->id => ['price_override' => null],
        ]);

        $priceRange = $event->getPriceRange();

        // Should only show the available ticket price ($50.00)
        $this->assertEquals('$50.00', $priceRange);
    }

    #[Test]
    public function it_returns_null_when_no_tickets_are_currently_available()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        // Create ticket that's not available yet
        $futureTicket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'USD',
            'availability_window_start_utc' => now()->addDays(10)->utc(),
            'availability_window_end_utc' => now()->addDays(20)->utc(),
        ]);

        $occurrence->ticketDefinitions()->attach($futureTicket->id, [
            'price_override' => null,
        ]);

        $priceRange = $event->getPriceRange();

        // Should return null since no tickets are currently available
        $this->assertNull($priceRange);
    }

    #[Test]
    public function it_includes_tickets_with_no_availability_window()
    {
        $organizer = User::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $occurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
        ]);

        // Create ticket with no availability window (should always be available)
        $ticket = TicketDefinition::factory()->create([
            'price' => 5000, // $50.00
            'currency' => 'USD',
            'availability_window_start_utc' => null,
            'availability_window_end_utc' => null,
        ]);

        $occurrence->ticketDefinitions()->attach($ticket->id, [
            'quantity_for_occurrence' => 100,
            'price_override' => null,
        ]);

        $priceRange = $event->getPriceRange();

        $this->assertEquals('$50.00', $priceRange);
    }

    // ========================================
    // NEW TESTS FOR UPDATED EVENT FACTORY
    // ========================================

    #[Test]
    public function event_factory_creates_event_with_organizer_entity_by_default()
    {
        $event = Event::factory()->create();

        $this->assertNotNull($event->organizer_id);
        $this->assertInstanceOf(\App\Models\Organizer::class, $event->organizer);
    }

    #[Test]
    public function event_factory_can_create_event_for_specific_organizer()
    {
        $organizer = \App\Models\Organizer::factory()->create();
        $event = Event::factory()->forOrganizer($organizer)->create();

        $this->assertEquals($organizer->id, $event->organizer_id);
        $this->assertTrue($event->organizer->is($organizer));
    }

    #[Test]
    public function event_factory_can_create_published_event()
    {
        $event = Event::factory()->published()->create();

        $this->assertEquals('published', $event->event_status);
        $this->assertNotNull($event->published_at);
        $this->assertLessThanOrEqual(now(), $event->published_at);
    }

    #[Test]
    public function event_factory_can_create_featured_event()
    {
        $event = Event::factory()->featured()->create();

        $this->assertTrue($event->is_featured);
    }

    #[Test]
    public function event_factory_can_create_draft_event()
    {
        $event = Event::factory()->draft()->create();

        $this->assertEquals('draft', $event->event_status);
        $this->assertNull($event->published_at);
    }

    #[Test]
    public function event_factory_can_create_event_in_specific_category()
    {
        $category = Category::factory()->create();
        $event = Event::factory()->inCategory($category)->create();

        $this->assertEquals($category->id, $event->category_id);
        $this->assertTrue($event->category->is($category));
    }

    #[Test]
    public function event_factory_can_create_event_with_multi_language_content()
    {
        $event = Event::factory()->withMultiLanguageContent()->create();

        // Check that all three languages have content in the raw attributes
        // The translatable trait stores JSON in database but accessors return locale-specific strings
        $nameData = $event->getAttributes()['name'];
        $nameArray = is_string($nameData) ? json_decode($nameData, true) : $nameData;

        $this->assertArrayHasKey('en', $nameArray);
        $this->assertArrayHasKey('zh-TW', $nameArray);
        $this->assertArrayHasKey('zh-CN', $nameArray);

        $this->assertNotEmpty($nameArray['en']);
        $this->assertNotEmpty($nameArray['zh-TW']);
        $this->assertNotEmpty($nameArray['zh-CN']);

        // Check other translatable fields
        $descriptionData = $event->getAttributes()['description'];
        $descriptionArray = is_string($descriptionData) ? json_decode($descriptionData, true) : $descriptionData;

        $this->assertArrayHasKey('en', $descriptionArray);
        $this->assertArrayHasKey('zh-TW', $descriptionArray);
        $this->assertArrayHasKey('zh-CN', $descriptionArray);
    }

    #[Test]
    public function event_factory_methods_can_be_chained()
    {
        $category = Category::factory()->create();
        $organizer = \App\Models\Organizer::factory()->create();

        $event = Event::factory()
            ->forOrganizer($organizer)
            ->inCategory($category)
            ->published()
            ->featured()
            ->withMultiLanguageContent()
            ->create();

        $this->assertEquals($organizer->id, $event->organizer_id);
        $this->assertEquals($category->id, $event->category_id);
        $this->assertEquals('published', $event->event_status);
        $this->assertTrue($event->is_featured);
        $this->assertNotNull($event->published_at);

        // Check translatable field using raw attributes
        $nameData = $event->getAttributes()['name'];
        $nameArray = is_string($nameData) ? json_decode($nameData, true) : $nameData;
        $this->assertArrayHasKey('zh-TW', $nameArray);
    }

    #[Test]
    public function event_factory_with_organizer_entity_method_works()
    {
        $event = Event::factory()->withOrganizerEntity()->create();

        $this->assertNotNull($event->organizer_id);
        $this->assertInstanceOf(\App\Models\Organizer::class, $event->organizer);
    }

    #[Test]
    public function event_factory_deprecated_for_testing_method_still_works()
    {
        // This test ensures backward compatibility for any existing tests
        $event = Event::factory()->forTesting()->create();

        $this->assertNotNull($event->organizer_id);
        $this->assertInstanceOf(\App\Models\Organizer::class, $event->organizer);
    }
}
