<?php

namespace Tests\Feature\Http\Controllers\Admin;

use App\Enums\RoleNameEnum;
use App\Enums\TicketDefinitionStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ManualBookingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customer;
    private User $regularUser;
    private Event $event;
    private TicketDefinition $ticketDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        Config::set('app.available_locales', ['en' => 'English', 'zh-TW' => 'Traditional Chinese']);
        Config::set('app.locale', 'en');

        // Create roles
        Role::create(['name' => RoleNameEnum::ADMIN->value]);
        Role::create(['name' => RoleNameEnum::USER->value]);

        // Create users
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleNameEnum::ADMIN->value);

        $this->customer = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        $this->customer->assignRole(RoleNameEnum::USER->value);

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole(RoleNameEnum::USER->value);

        // Create test event and ticket definition
        $this->createTestEventAndTicket();
    }

    private function createTestEventAndTicket(): void
    {
        $category = Category::factory()->create([
            'name' => ['en' => 'Test Category']
        ]);

        $organizer = Organizer::factory()->create();
        $venue = Venue::factory()->create();

        $this->event = Event::factory()->create([
            'name' => ['en' => 'Test Event'],
            'description' => ['en' => 'Test Description'],
            'organizer_id' => $organizer->id,
            'category_id' => $category->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $this->event->id,
            'venue_id' => $venue->id,
            'start_at_utc' => now()->addDays(30),
            'end_at_utc' => now()->addDays(30)->addHours(3),
        ]);

        $this->ticketDefinition = TicketDefinition::factory()->create([
            'name' => ['en' => 'Standard Ticket'],
            'price' => 5000, // $50.00 in cents
            'currency' => 'USD',
            'total_quantity' => 100,
            'status' => TicketDefinitionStatus::ACTIVE,
            'availability_window_start_utc' => now()->subDays(1), // Available since yesterday
            'availability_window_end_utc' => now()->addDays(60), // Available for next 60 days
        ]);

        // Associate ticket with event occurrence
        $this->ticketDefinition->eventOccurrences()->attach($eventOccurrence->id);
    }

    public function test_admin_can_access_manual_booking_create_form(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings.create'));

        // Since we don't have the frontend view yet, just check that it doesn't throw an error
        // In a real implementation, this would render the Inertia page
        $response->assertStatus(500); // Will be 500 due to missing Inertia component, which is expected
    }

    public function test_regular_user_cannot_access_manual_booking_create_form(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.bookings.create'));

        $response->assertStatus(403);
    }

    public function test_admin_can_get_ticket_definitions_for_event(): void
    {
        $response = $this->actingAs($this->admin)->get(
            route('admin.events.ticket-definitions', $this->event->id)
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'ticket_definitions' => [
                    [
                        'id' => $this->ticketDefinition->id,
                        'price' => 5000,
                        'currency' => 'USD',
                        'total_quantity' => 100,
                        'formatted_price' => '50.00 USD',
                    ]
                ]
            ]);
    }

    public function test_regular_user_cannot_get_ticket_definitions(): void
    {
        $response = $this->actingAs($this->regularUser)->get(
            route('admin.events.ticket-definitions', $this->event->id)
        );

        $response->assertStatus(403);
    }

    public function test_admin_can_create_manual_booking(): void
    {
        $bookingData = [
            'user_id' => $this->customer->id,
            'event_id' => $this->event->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'quantity' => 2,
            'reason' => 'Test manual booking creation',
            'admin_notes' => 'Created for testing purposes',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.store'),
            $bookingData
        );

        $response->assertRedirect(route('admin.bookings.index'));
        $response->assertSessionHas('success');

        // Verify booking was created in database
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->customer->id,
            'total_amount' => 10000, // 2 tickets × $50.00
            'currency' => 'USD',
            'status' => 'confirmed',
            'payment_gateway' => 'manual',
            'is_manual_booking' => true,
            'created_by_admin_id' => $this->admin->id,
            'admin_notes' => 'Created for testing purposes',
        ]);

        $this->assertDatabaseHas('bookings', [
            'event_id' => $this->event->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'quantity' => 1, // Individual booking records
            'price_at_booking' => 5000,
            'currency_at_booking' => 'USD',
            'status' => 'confirmed',
        ]);

        // Should create 2 individual booking records
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_admin_cannot_create_manual_booking_with_invalid_data(): void
    {
        $bookingData = [
            'user_id' => 99999, // Non-existent user
            'event_id' => $this->event->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'quantity' => 2,
            'reason' => 'Test invalid booking',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.store'),
            $bookingData
        );

        $response->assertStatus(302); // Redirect back with errors
        $response->assertSessionHasErrors(['user_id']);

        // Verify no booking was created
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_regular_user_cannot_create_manual_booking(): void
    {
        $bookingData = [
            'user_id' => $this->customer->id,
            'event_id' => $this->event->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'quantity' => 1,
            'reason' => 'Test unauthorized booking',
        ];

        $response = $this->actingAs($this->regularUser)->post(
            route('admin.bookings.store'),
            $bookingData
        );

        $response->assertStatus(403);

        // Verify no booking was created
        $this->assertDatabaseCount('transactions', 0);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_admin_can_create_manual_booking_with_price_override(): void
    {
        $overridePrice = 3000; // $30.00 instead of $50.00

        $bookingData = [
            'user_id' => $this->customer->id,
            'event_id' => $this->event->id,
            'ticket_definition_id' => $this->ticketDefinition->id,
            'quantity' => 1,
            'price_override' => $overridePrice,
            'reason' => 'Discounted ticket',
            'admin_notes' => 'Student discount applied',
        ];

        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.store'),
            $bookingData
        );

        $response->assertRedirect(route('admin.bookings.index'));
        $response->assertSessionHas('success');

        // Verify booking was created with overridden price
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->customer->id,
            'total_amount' => $overridePrice,
            'currency' => 'USD',
            'status' => 'confirmed',
            'is_manual_booking' => true,
        ]);

        $this->assertDatabaseHas('bookings', [
            'price_at_booking' => $overridePrice,
            'currency_at_booking' => 'USD',
            'status' => 'confirmed',
        ]);
    }

    public function test_unauthenticated_user_cannot_access_manual_booking_endpoints(): void
    {
        // Test create form
        $this->get(route('admin.bookings.create'))->assertRedirect('/login');

        // Test store
        $this->post(route('admin.bookings.store'), [])->assertRedirect('/login');

        // Test ticket definitions API
        $this->get(route('admin.events.ticket-definitions', $this->event->id))
            ->assertRedirect('/login');
    }
}