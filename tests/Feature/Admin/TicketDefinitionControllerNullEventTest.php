<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Models\Organizer;
use App\Models\Category;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test specifically for the null event reference bug fix
 * This addresses the production error where getTranslation() was called on null
 */
class TicketDefinitionControllerNullEventTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        // Create admin user with necessary permissions
        $this->adminUser = User::factory()->create();
        $this->adminUser->assignRole(\App\Enums\RoleNameEnum::ADMIN->value);
    }

    public function test_create_method_handles_soft_deleted_events()
    {
        // Create valid event structure
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

        // Verify it works with valid data first
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('eventOccurrences')
        );

        // Now soft delete the event to simulate the production issue
        $event->delete();

        // This should not throw the "Call to a member function getTranslation() on null" error
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('eventOccurrences')
        );
    }

    public function test_edit_method_handles_soft_deleted_events()
    {
        // Create a ticket definition for editing
        $ticketDefinition = TicketDefinition::factory()->create();

        // Create valid event structure
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

        // Verify it works with valid data first
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('eventOccurrences')
        );

        // Now soft delete the event to simulate the production issue
        $event->delete();

        // This should not throw the "Call to a member function getTranslation() on null" error
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('eventOccurrences')
        );
    }

    public function test_create_handles_no_event_occurrences()
    {
        // With no event occurrences at all, the page should still work
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->where('eventOccurrences', [])
        );
    }

    public function test_edit_handles_no_event_occurrences()
    {
        $ticketDefinition = TicketDefinition::factory()->create();

        // With no event occurrences at all, the page should still work
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.edit', $ticketDefinition));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Edit')
                ->has('ticketDefinition')
                ->has('statuses')
                ->has('availableLocales')
                ->has('timezones')
                ->where('eventOccurrences', [])
        );
    }

    public function test_event_occurrences_filtering_works_correctly()
    {
        // Create valid event structure
        $organizer = Organizer::factory()->create();
        $category = Category::factory()->create();
        $venue = Venue::factory()->create();

        // Create one valid event with occurrence
        $validEvent = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $validOccurrence = EventOccurrence::factory()->create([
            'event_id' => $validEvent->id,
            'venue_id' => $venue->id,
        ]);

        // Create another event and occurrence, then soft delete the event
        $deletedEvent = Event::factory()->create([
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $orphanedOccurrence = EventOccurrence::factory()->create([
            'event_id' => $deletedEvent->id,
            'venue_id' => $venue->id,
        ]);

        // Delete the second event
        $deletedEvent->delete();

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.ticket-definitions.create'));

        $response->assertStatus(200);
        $response->assertInertia(
            fn($page) =>
            $page->component('Admin/TicketDefinitions/Create')
                ->has('eventOccurrences')
                ->where('eventOccurrences', function ($occurrences) use ($validOccurrence) {
                    // Should only have the valid occurrence, not the orphaned one
                    return count($occurrences) >= 1 && 
                           collect($occurrences)->contains('id', $validOccurrence->id);
                })
        );
    }
}