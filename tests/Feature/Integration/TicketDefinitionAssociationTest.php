<?php

namespace Tests\Feature;

use App\Actions\TicketDefinition\UpsertTicketDefinitionAction;
use App\DataTransferObjects\TicketDefinitionData;
use App\Enums\TicketDefinitionStatus;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User; // Assuming a User model exists for event organizer or other relations
use App\Models\Venue; // Assuming a Venue model exists for event occurrences
use App\Models\Category; // Assuming a Category model exists for events
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TicketDefinitionAssociationTest extends TestCase
{
    use RefreshDatabase;

    private UpsertTicketDefinitionAction $upsertAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->upsertAction = $this->app->make(UpsertTicketDefinitionAction::class);

        // Ensure necessary locales are available for translatable fields
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');
    }

    private function createPrerequisites(): array
    {
        // It's good practice to have factories, but for simplicity, we'll create directly
        // or assume basic factories exist.
        $user = User::factory()->create(); // Assuming User factory exists
        $category = Category::factory()->create(['name' => ['en' => 'Test Category']]); // Assuming Category factory exists
        $event = Event::factory()->create([ // Assuming Event factory exists
            'organizer_id' => $user->id,
            'category_id' => $category->id,
            'name' => ['en' => 'Test Event'],
            'slug' => ['en' => 'test-event'],
            'description' => ['en' => 'Event Description'],
            'short_summary' => ['en' => 'Short Summary'],
            'event_status' => 'draft', // Ensure this matches your Event model's expected values/enum
            'visibility' => 'public',
            'is_featured' => false,
            'cancellation_policy' => ['en' => 'Cancellation Policy'],
            'meta_title' => ['en' => 'Meta Title'],
            'meta_description' => ['en' => 'Meta Description'],
            'meta_keywords' => ['en' => 'keywords, test'],
        ]);
        $venue = Venue::factory()->create(); // Assuming Venue factory exists

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Occurrence 1'],
            'start_at_utc' => now()->addDays(10),
            'end_at_utc' => now()->addDays(10)->addHours(2),
            'timezone' => 'UTC',
        ]);
        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'Occurrence 2'],
            'start_at_utc' => now()->addDays(11),
            'end_at_utc' => now()->addDays(11)->addHours(2),
            'timezone' => 'UTC',
        ]);

        return ['event' => $event, 'occurrence1' => $occurrence1, 'occurrence2' => $occurrence2, 'user' => $user, 'venue' => $venue];
    }

    public function test_can_create_ticket_definition_and_associate_with_event_occurrences(): void
    {
        $prerequisites = $this->createPrerequisites();
        $occurrence1 = $prerequisites['occurrence1'];
        $occurrence2 = $prerequisites['occurrence2'];

        $ticketData = new TicketDefinitionData(
            id: null,
            name: ['en' => 'Early Bird Ticket', 'zh-TW' => '早鳥票'],
            description: ['en' => 'Limited early bird tickets', 'zh-TW' => '限量早鳥票'],
            price: 1000, // e.g., cents
            totalQuantity: 100,
            availabilityWindowStart: null,
            availabilityWindowEnd: null,
            minPerOrder: 1,
            maxPerOrder: 5,
            status: TicketDefinitionStatus::ACTIVE,
            metadata: null,
            eventOccurrenceIds: [$occurrence1->id, $occurrence2->id]
        );

        $ticketDefinition = $this->upsertAction->execute($ticketData);

        $this->assertDatabaseHas('ticket_definitions', [
            'id' => $ticketDefinition->id,
            'price' => 1000,
        ]);
        // Check for one of the translatable names
        $this->assertEquals('Early Bird Ticket', $ticketDefinition->getTranslation('name', 'en'));


        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $ticketDefinition->id,
            'event_occurrence_id' => $occurrence1->id,
        ]);
        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $ticketDefinition->id,
            'event_occurrence_id' => $occurrence2->id,
        ]);

        $this->assertCount(2, $ticketDefinition->eventOccurrences);
    }

    public function test_can_update_ticket_definition_and_change_event_occurrence_associations(): void
    {
        $prerequisites = $this->createPrerequisites();
        $event = $prerequisites['event'];
        $venue = $prerequisites['venue'];
        $initialOccurrence1 = $prerequisites['occurrence1'];
        $initialOccurrence2 = $prerequisites['occurrence2'];

        // 1. Create initial ticket definition associated with occurrence1 and occurrence2
        $initialTicketData = new TicketDefinitionData(
            id: null,
            name: ['en' => 'Initial Ticket'],
            description: ['en' => 'Initial description'],
            price: 1500,
            totalQuantity: 50,
            availabilityWindowStart: null,
            availabilityWindowEnd: null,
            minPerOrder: 1,
            maxPerOrder: 10,
            status: TicketDefinitionStatus::ACTIVE,
            metadata: null,
            eventOccurrenceIds: [$initialOccurrence1->id, $initialOccurrence2->id]
        );
        $ticketDefinition = $this->upsertAction->execute($initialTicketData);

        $this->assertCount(2, $ticketDefinition->eventOccurrences, 'Initial association failed');
        $this->assertTrue($ticketDefinition->eventOccurrences->contains($initialOccurrence1));
        $this->assertTrue($ticketDefinition->eventOccurrences->contains($initialOccurrence2));

        // 2. Create new occurrences for updating
        $newOccurrence3 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'New Occurrence 3'],
            'start_at_utc' => now()->addDays(12),
            'end_at_utc' => now()->addDays(12)->addHours(2),
            'timezone' => 'UTC',
        ]);
        $newOccurrence4 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'venue_id' => $venue->id,
            'name' => ['en' => 'New Occurrence 4'],
            'start_at_utc' => now()->addDays(13),
            'end_at_utc' => now()->addDays(13)->addHours(2),
            'timezone' => 'UTC',
        ]);

        // 3. Update ticket definition to associate with newOccurrence3 and newOccurrence4
        $updateTicketData = new TicketDefinitionData(
            id: $ticketDefinition->id, // Crucial: provide the ID for update
            name: ['en' => 'Updated Ticket Name'], // Also update another field
            description: $ticketDefinition->getTranslation('description', 'en') ? ['en' => $ticketDefinition->getTranslation('description', 'en')] : null, // Preserve or use a new one
            price: 1600,
            totalQuantity: $ticketDefinition->total_quantity, // Preserve or update
            availabilityWindowStart: $ticketDefinition->availability_window_start, // Preserve or update
            availabilityWindowEnd: $ticketDefinition->availability_window_end, // Preserve or update
            minPerOrder: $ticketDefinition->min_per_order, // Preserve or update
            maxPerOrder: $ticketDefinition->max_per_order, // Preserve or update
            status: $ticketDefinition->status, // Preserve or update
            metadata: $ticketDefinition->metadata, // Preserve or update
            eventOccurrenceIds: [$newOccurrence3->id, $newOccurrence4->id]
        );

        $updatedTicketDefinition = $this->upsertAction->execute($updateTicketData, $ticketDefinition->id);

        $this->assertEquals('Updated Ticket Name', $updatedTicketDefinition->getTranslation('name', 'en'));
        $this->assertEquals(1600, $updatedTicketDefinition->price);

        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $newOccurrence3->id,
        ]);
        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $newOccurrence4->id,
        ]);
        $this->assertDatabaseMissing('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence1->id,
        ]);
        $this->assertDatabaseMissing('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence2->id,
        ]);

        $this->assertCount(2, $updatedTicketDefinition->eventOccurrences);
        $this->assertTrue($updatedTicketDefinition->eventOccurrences->contains($newOccurrence3));
        $this->assertTrue($updatedTicketDefinition->eventOccurrences->contains($newOccurrence4));
        $this->assertFalse($updatedTicketDefinition->eventOccurrences->contains($initialOccurrence1));
        $this->assertFalse($updatedTicketDefinition->eventOccurrences->contains($initialOccurrence2));
    }

    public function test_can_update_ticket_definition_and_detach_all_event_occurrences(): void
    {
        $prerequisites = $this->createPrerequisites();
        $initialOccurrence1 = $prerequisites['occurrence1'];
        $initialOccurrence2 = $prerequisites['occurrence2'];

        // 1. Create initial ticket definition associated with occurrence1 and occurrence2
        $initialTicketData = new TicketDefinitionData(
            id: null,
            name: ['en' => 'Ticket to Detach'],
            description: ['en' => 'This ticket will have its occurrences detached'],
            price: 2000,
            totalQuantity: 20,
            availabilityWindowStart: null,
            availabilityWindowEnd: null,
            minPerOrder: 1,
            maxPerOrder: 2,
            status: TicketDefinitionStatus::ACTIVE,
            metadata: null,
            eventOccurrenceIds: [$initialOccurrence1->id, $initialOccurrence2->id]
        );
        $ticketDefinition = $this->upsertAction->execute($initialTicketData);
        $this->assertCount(2, $ticketDefinition->eventOccurrences, 'Initial association for detach test failed');

        // 2. Update ticket definition with empty array for eventOccurrenceIds
        $updateTicketData = new TicketDefinitionData(
            id: $ticketDefinition->id,
            name: $ticketDefinition->getTranslation('name', 'en') ? ['en' => $ticketDefinition->getTranslation('name', 'en')] : ['en' => 'Still Ticket to Detach'],
            description: $ticketDefinition->getTranslation('description', 'en') ? ['en' => $ticketDefinition->getTranslation('description', 'en')] : null,
            price: $ticketDefinition->price,
            totalQuantity: $ticketDefinition->total_quantity,
            availabilityWindowStart: $ticketDefinition->availability_window_start,
            availabilityWindowEnd: $ticketDefinition->availability_window_end,
            minPerOrder: $ticketDefinition->min_per_order,
            maxPerOrder: $ticketDefinition->max_per_order,
            status: $ticketDefinition->status,
            metadata: $ticketDefinition->metadata,
            eventOccurrenceIds: [] // Empty array to detach all
        );

        $updatedTicketDefinition = $this->upsertAction->execute($updateTicketData, $ticketDefinition->id);

        $this->assertDatabaseMissing('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence1->id,
        ]);
        $this->assertDatabaseMissing('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence2->id,
        ]);
        $this->assertCount(0, $updatedTicketDefinition->eventOccurrences);
    }

    public function test_can_update_ticket_definition_details_without_affecting_associations(): void
    {
        $prerequisites = $this->createPrerequisites();
        $initialOccurrence1 = $prerequisites['occurrence1'];
        $initialOccurrence2 = $prerequisites['occurrence2'];

        // 1. Create initial ticket definition associated with occurrence1 and occurrence2
        $initialTicketData = new TicketDefinitionData(
            id: null,
            name: ['en' => 'Ticket with Stable Associations'],
            description: ['en' => 'Associations should not change'],
            price: 2500,
            totalQuantity: 30,
            availabilityWindowStart: null,
            availabilityWindowEnd: null,
            minPerOrder: 1,
            maxPerOrder: 3,
            status: TicketDefinitionStatus::ACTIVE,
            metadata: null,
            eventOccurrenceIds: [$initialOccurrence1->id, $initialOccurrence2->id]
        );
        $ticketDefinition = $this->upsertAction->execute($initialTicketData);
        $this->assertCount(2, $ticketDefinition->eventOccurrences, 'Initial association for no-change test failed');

        // 2. Update ticket definition details, setting eventOccurrenceIds to null (or omitting it)
        $updatedPrice = 2600;
        $updatedName = ['en' => 'Updated Name, Stable Associations'];

        $updateTicketData = new TicketDefinitionData(
            id: $ticketDefinition->id,
            name: $updatedName,
            description: $ticketDefinition->getTranslation('description', 'en') ? ['en' => $ticketDefinition->getTranslation('description', 'en')] : null,
            price: $updatedPrice,
            totalQuantity: $ticketDefinition->total_quantity,
            availabilityWindowStart: $ticketDefinition->availability_window_start,
            availabilityWindowEnd: $ticketDefinition->availability_window_end,
            minPerOrder: $ticketDefinition->min_per_order,
            maxPerOrder: $ticketDefinition->max_per_order,
            status: $ticketDefinition->status,
            metadata: $ticketDefinition->metadata,
            eventOccurrenceIds: null // Explicitly null to indicate no change to associations
        );

        $updatedTicketDefinition = $this->upsertAction->execute($updateTicketData, $ticketDefinition->id);

        // Assert details changed
        $this->assertEquals($updatedName['en'], $updatedTicketDefinition->getTranslation('name', 'en'));
        $this->assertEquals($updatedPrice, $updatedTicketDefinition->price);

        // Assert associations remain unchanged
        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence1->id,
        ]);
        $this->assertDatabaseHas('event_occurrence_ticket_definition', [
            'ticket_definition_id' => $updatedTicketDefinition->id,
            'event_occurrence_id' => $initialOccurrence2->id,
        ]);
        $this->assertCount(2, $updatedTicketDefinition->eventOccurrences);
        $this->assertTrue($updatedTicketDefinition->eventOccurrences->contains($initialOccurrence1));
        $this->assertTrue($updatedTicketDefinition->eventOccurrences->contains($initialOccurrence2));
    }

    // Optional: Test for validation failure with non-existent occurrence IDs
    // This is primarily handled by DTO validation rules but can be confirmed at feature level.
    public function test_associating_with_non_existent_event_occurrence_id_throws_validation_exception(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $nonExistentOccurrenceId = 99999; // An ID that certainly does not exist

        $payload = [
            // id: null, // Not needed for ::from, will be null by default if not provided
            'name' => ['en' => 'Test Ticket Validation'],
            'description' => ['en' => 'Description'],
            'price' => 500,
            'total_quantity' => 10,
            // availabilityWindowStart: null, // These can be omitted if truly optional and nullable in DTO
            // availabilityWindowEnd: null,
            'min_per_order' => 1,
            // maxPerOrder: 1, // Optional
            'status' => TicketDefinitionStatus::ACTIVE->value, // Pass the enum value for ::from
            // metadata: null, // Optional
            'event_occurrence_ids' => [$nonExistentOccurrenceId]
        ];

        // Attempt to create the DTO from payload; this should trigger validation
        TicketDefinitionData::validate($payload);

        // The test will fail if ValidationException is not thrown before this point.
        // No need to call the action, as the DTO validation itself should fail.
    }
}
