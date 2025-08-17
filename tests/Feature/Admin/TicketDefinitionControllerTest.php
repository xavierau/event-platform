<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Models\Venue;
use App\Enums\TicketDefinitionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDefinitionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Event $event;
    protected EventOccurrence $eventOccurrence;
    protected TicketDefinition $ticketDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create admin user with necessary permissions
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(\App\Enums\RoleNameEnum::ADMIN->value);

        // Create basic event structure for testing
        $organizer = Organizer::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        $this->event = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $this->eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $this->event->id,
            'venue_id' => $venue->id,
        ]);

        $this->ticketDefinition = TicketDefinition::factory()->create();
    }

    public function test_index_displays_ticket_definitions()
    {
        $ticketDefinitions = TicketDefinition::factory()->count(3)->create();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.index'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Index')
                ->has('ticketDefinitions.data', 4) // 3 created + 1 from setUp
        );
    }

    public function test_create_displays_form_with_valid_event_occurrences_only()
    {
        // Soft delete the event to simulate the production issue
        $this->event->delete();
        
        // Create a new valid event and occurrence
        $organizer = Organizer::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();
        
        $validEvent = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);
        $validOccurrence = EventOccurrence::factory()->create([
            'event_id' => $validEvent->id,
            'venue_id' => $venue->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->has('eventOccurrences')
                // Should only have 1 valid occurrence (the orphaned one should be filtered out)
                ->where('eventOccurrences', function ($occurrences) use ($validOccurrence) {
                    return count($occurrences) === 1 && $occurrences[0]['id'] === $validOccurrence->id;
                })
        );
    }

    public function test_create_handles_all_occurrences_with_deleted_events()
    {
        // Delete all events to simulate the worst-case scenario
        Event::query()->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->where('eventOccurrences', []) // Should be empty array
        );
    }

    public function test_edit_displays_form_with_valid_event_occurrences_only()
    {
        // Soft delete the event to simulate the production issue
        $this->event->delete();
        
        // Create a new valid event and occurrence
        $organizer = Organizer::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();
        
        $validEvent = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);
        $validOccurrence = EventOccurrence::factory()->create([
            'event_id' => $validEvent->id,
            'venue_id' => $venue->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $this->ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('ticketDefinition')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->has('eventOccurrences')
                // Should only have 1 valid occurrence
                ->where('eventOccurrences', function ($occurrences) use ($validOccurrence) {
                    return count($occurrences) === 1 && $occurrences[0]['id'] === $validOccurrence->id;
                })
        );
    }

    public function test_edit_handles_all_occurrences_with_deleted_events()
    {
        // Delete all events to simulate the worst-case scenario
        Event::query()->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $this->ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('ticketDefinition')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->where('eventOccurrences', []) // Should be empty array
        );
    }

    public function test_store_creates_ticket_definition()
    {
        $ticketData = [
            'name' => [
                'en' => 'Test Ticket EN',
                'zh-TW' => 'Test Ticket TW',
            ],
            'description' => [
                'en' => 'Test description EN',
                'zh-TW' => 'Test description TW',
            ],
            'price' => 5000, // In cents
            'currency' => 'HKD',
            'total_quantity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'status' => TicketDefinitionStatus::ACTIVE->value,
            'timezone' => 'Asia/Hong_Kong',
        ];

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.ticket-definitions.store'), array_merge($ticketData, ['_token' => 'test-token']));

        $response->assertRedirect(route('admin.ticket-definitions.index'));
        $response->assertSessionHas('success', 'Ticket definition created successfully.');

        $this->assertDatabaseHas('ticket_definitions', [
            'price' => 5000,
            'currency' => 'HKD',
            'total_quantity' => 100,
        ]);
    }

    public function test_update_modifies_ticket_definition()
    {
        $updateData = [
            'name' => [
                'en' => 'Updated Ticket EN',
                'zh-TW' => 'Updated Ticket TW',
            ],
            'description' => [
                'en' => 'Updated description EN',
            ],
            'price' => 7500,
            'currency' => 'USD',
            'total_quantity' => 50,
            'min_per_order' => 2,
            'max_per_order' => 5,
            'status' => TicketDefinitionStatus::INACTIVE->value,
            'timezone' => 'UTC',
        ];

        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => 'test-token'])
            ->put(route('admin.ticket-definitions.update', $this->ticketDefinition), array_merge($updateData, ['_token' => 'test-token']));

        $response->assertRedirect(route('admin.ticket-definitions.index'));
        $response->assertSessionHas('success', 'Ticket definition updated successfully.');

        $this->ticketDefinition->refresh();
        $this->assertEquals(7500, $this->ticketDefinition->price);
        $this->assertEquals('USD', $this->ticketDefinition->currency);
        $this->assertEquals(50, $this->ticketDefinition->total_quantity);
    }

    public function test_destroy_deletes_ticket_definition()
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['_token' => 'test-token'])
            ->delete(route('admin.ticket-definitions.destroy', $this->ticketDefinition), ['_token' => 'test-token']);

        $response->assertRedirect(route('admin.ticket-definitions.index'));
        $response->assertSessionHas('success', 'Ticket definition deleted successfully.');

        $this->assertSoftDeleted('ticket_definitions', ['id' => $this->ticketDefinition->id]);
    }

    public function test_index_can_filter_by_search()
    {
        TicketDefinition::factory()->create([
            'name' => ['en' => 'Searchable Ticket']
        ]);
        TicketDefinition::factory()->create([
            'name' => ['en' => 'Other Ticket']
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.index', ['search' => 'Searchable']));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->has('ticketDefinitions.data', 1)
                ->where('filters.search', 'Searchable')
        );
    }

    public function test_index_can_filter_by_status()
    {
        TicketDefinition::factory()->create(['status' => TicketDefinitionStatus::ACTIVE]);
        TicketDefinition::factory()->create(['status' => TicketDefinitionStatus::INACTIVE]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.index', ['status' => TicketDefinitionStatus::ACTIVE->value]));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->has('ticketDefinitions.data', 2) // 1 created + 1 from setUp
                ->where('filters.status', TicketDefinitionStatus::ACTIVE->value)
        );
    }

    public function test_create_handles_empty_event_occurrences_gracefully()
    {
        // Delete all event occurrences
        EventOccurrence::query()->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->where('eventOccurrences', [])
        );
    }

    public function test_edit_handles_empty_event_occurrences_gracefully()
    {
        // Delete all event occurrences
        EventOccurrence::query()->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $this->ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->where('eventOccurrences', [])
        );
    }

    /**
     * Test the specific scenario that caused the production error:
     * EventOccurrence exists but its related Event is null/deleted
     */
    public function test_create_filters_occurrences_with_soft_deleted_events()
    {
        // Create additional occurrence and then soft delete its event
        $organizer = Organizer::factory()->create();
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

        // Soft delete the event - this simulates the production scenario
        $event->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('eventOccurrences')
                // The soft-deleted event's occurrences should be filtered out
        );
    }

    /**
     * Test the same scenario for the edit method
     */
    public function test_edit_filters_occurrences_with_soft_deleted_events()
    {
        // Create additional occurrence and then soft delete its event
        $organizer = Organizer::factory()->create();
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

        // Soft delete the event - this simulates the production scenario
        $event->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $this->ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('eventOccurrences')
                // The soft-deleted event's occurrences should be filtered out
        );
    }
}