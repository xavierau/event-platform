<?php

namespace Tests\Feature;

use App\DataTransferObjects\Booking\InitiateBookingData;
use App\DataTransferObjects\Booking\BookingRequestItemData;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\UserMembership;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

class BookingServiceMembershipDiscountTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bookingService = app(BookingService::class);
    }

    /** @test */
    public function booking_service_uses_membership_price_for_members(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $occurrence = EventOccurrence::factory()->create();

        // Associate ticket with occurrence
        $ticketDefinition->eventOccurrences()->attach($occurrence->id);

        $membershipLevel = MembershipLevel::factory()->create([
            'name' => ['en' => 'Premium'],
        ]);

        // Create active membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create 20% discount
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $bookingData = new InitiateBookingData(
            occurrence_id: $occurrence->id,
            items: new DataCollection(BookingRequestItemData::class, [
                new BookingRequestItemData(
                    ticket_id: $ticketDefinition->id,
                    quantity: 2,
                    price_at_purchase: 100.00, // This will be ignored in favor of server-side calculation
                    name: 'Test Ticket'
                )
            ])
        );

        $result = $this->bookingService->processBookingInitiation($user, $bookingData);

        // Should require payment with discounted price
        $this->assertTrue($result['requires_payment']);
        $this->assertArrayHasKey('checkout_url', $result);

        // Check that booking was created with discounted price
        $this->assertDatabaseHas('bookings', [
            'ticket_definition_id' => $ticketDefinition->id,
            'price_at_booking' => 8000, // $80.00 (20% off $100)
        ]);

        // Check booking metadata stores original price
        $booking = \App\Models\Booking::where('ticket_definition_id', $ticketDefinition->id)->first();
        $this->assertNotNull($booking->metadata);
        $this->assertEquals(10000, $booking->metadata['original_price']);
        $this->assertTrue($booking->metadata['membership_discount_applied']);
        $this->assertEquals(2000, $booking->metadata['discount_amount']);
    }

    /** @test */
    public function booking_service_uses_regular_price_for_non_members(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $occurrence = EventOccurrence::factory()->create();

        // Associate ticket with occurrence
        $ticketDefinition->eventOccurrences()->attach($occurrence->id);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create discount but user has no membership
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'percentage',
            'discount_value' => 20,
        ]);

        $bookingData = new InitiateBookingData(
            occurrence_id: $occurrence->id,
            items: new DataCollection(BookingRequestItemData::class, [
                new BookingRequestItemData(
                    ticket_id: $ticketDefinition->id,
                    quantity: 1,
                    price_at_purchase: 100.00,
                    name: 'Test Ticket'
                )
            ])
        );

        $result = $this->bookingService->processBookingInitiation($user, $bookingData);

        $this->assertTrue($result['requires_payment']);

        // Check that booking was created with regular price
        $this->assertDatabaseHas('bookings', [
            'ticket_definition_id' => $ticketDefinition->id,
            'price_at_booking' => 10000, // Regular price
        ]);

        // Check booking metadata
        $booking = \App\Models\Booking::where('ticket_definition_id', $ticketDefinition->id)->first();
        $this->assertEquals(10000, $booking->metadata['original_price']);
        $this->assertFalse($booking->metadata['membership_discount_applied']);
        $this->assertEquals(0, $booking->metadata['discount_amount']);
    }

    /** @test */
    public function booking_service_handles_fixed_amount_discount(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 10000, // $100.00
        ]);

        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition->eventOccurrences()->attach($occurrence->id);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create active membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create fixed discount of $25
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 2500, // $25.00
        ]);

        $bookingData = new InitiateBookingData(
            occurrence_id: $occurrence->id,
            items: new DataCollection(BookingRequestItemData::class, [
                new BookingRequestItemData(
                    ticket_id: $ticketDefinition->id,
                    quantity: 1,
                    price_at_purchase: 100.00,
                    name: 'Test Ticket'
                )
            ])
        );

        $result = $this->bookingService->processBookingInitiation($user, $bookingData);

        $this->assertTrue($result['requires_payment']);

        // Check that booking was created with discounted price
        $this->assertDatabaseHas('bookings', [
            'ticket_definition_id' => $ticketDefinition->id,
            'price_at_booking' => 7500, // $75.00 ($100 - $25)
        ]);
    }

    /** @test */
    public function booking_service_creates_free_booking_when_discount_covers_full_price(): void
    {
        $user = User::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create([
            'price' => 2000, // $20.00
        ]);

        $occurrence = EventOccurrence::factory()->create();
        $ticketDefinition->eventOccurrences()->attach($occurrence->id);

        $membershipLevel = MembershipLevel::factory()->create();

        // Create active membership
        UserMembership::factory()->create([
            'user_id' => $user->id,
            'membership_level_id' => $membershipLevel->id,
            'status' => 'active',
            'expires_at' => now()->addMonths(1),
        ]);

        // Create discount that covers full price
        $ticketDefinition->membershipDiscounts()->attach($membershipLevel->id, [
            'discount_type' => 'fixed',
            'discount_value' => 2500, // $25.00 (more than ticket price)
        ]);

        $bookingData = new InitiateBookingData(
            occurrence_id: $occurrence->id,
            items: new DataCollection(BookingRequestItemData::class, [
                new BookingRequestItemData(
                    ticket_id: $ticketDefinition->id,
                    quantity: 1,
                    price_at_purchase: 20.00,
                    name: 'Test Ticket'
                )
            ])
        );

        $result = $this->bookingService->processBookingInitiation($user, $bookingData);

        // Should not require payment
        $this->assertFalse($result['requires_payment']);
        $this->assertTrue($result['booking_confirmed']);

        // Check that booking was created with zero price
        $this->assertDatabaseHas('bookings', [
            'ticket_definition_id' => $ticketDefinition->id,
            'price_at_booking' => 0, // Free after discount
        ]);

        // Check booking metadata
        $booking = \App\Models\Booking::where('ticket_definition_id', $ticketDefinition->id)->first();
        $this->assertEquals(2000, $booking->metadata['original_price']);
        $this->assertTrue($booking->metadata['membership_discount_applied']);
        $this->assertEquals(2000, $booking->metadata['discount_amount']);
    }
}