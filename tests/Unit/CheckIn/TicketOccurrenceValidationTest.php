<?php

namespace Tests\Unit\CheckIn;

use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Services\CheckInEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TicketOccurrenceValidationTest extends TestCase
{
    use RefreshDatabase;

    private CheckInEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibilityService = new CheckInEligibilityService();

        // Create roles for testing
        Role::create(['name' => RoleNameEnum::ADMIN->value]);
        Role::create(['name' => RoleNameEnum::ORGANIZER->value]);
    }

    /** @test */
    public function it_allows_check_in_for_ticket_at_its_designated_occurrence()
    {
        // Create Event A with two occurrences
        $eventA = Event::factory()->create(['name' => 'Event A']);

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(2),
        ]);

        // Create Ticket A.1 (only for occurrence 1)
        $ticketA1 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.1 - Occurrence 1 Only'],
        ]);

        // Associate ticket A.1 only with occurrence 1
        $ticketA1->eventOccurrences()->attach($occurrence1->id);

        // Create Ticket A.2 (only for occurrence 2)
        $ticketA2 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.2 - Occurrence 2 Only'],
        ]);

        // Associate ticket A.2 only with occurrence 2
        $ticketA2->eventOccurrences()->attach($occurrence2->id);

        // Create booking for Ticket A.1
        $bookingA1 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA1->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Create booking for Ticket A.2
        $bookingA2 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA2->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Test: Booking A.1 should be allowed at occurrence 1
        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA1, $occurrence1);
        $this->assertTrue($result1['is_eligible'], 'Booking A.1 should be eligible for occurrence 1');
        $this->assertEmpty($result1['errors']);

        // Test: Booking A.2 should be allowed at occurrence 2
        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA2, $occurrence2);
        $this->assertTrue($result2['is_eligible'], 'Booking A.2 should be eligible for occurrence 2');
        $this->assertEmpty($result2['errors']);
    }

    /** @test */
    public function it_rejects_check_in_for_ticket_at_wrong_occurrence()
    {
        // Create Event A with two occurrences
        $eventA = Event::factory()->create(['name' => 'Event A']);

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(2),
        ]);

        // Create Ticket A.1 (only for occurrence 1)
        $ticketA1 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.1 - Occurrence 1 Only'],
        ]);

        // Associate ticket A.1 only with occurrence 1
        $ticketA1->eventOccurrences()->attach($occurrence1->id);

        // Create Ticket A.2 (only for occurrence 2)
        $ticketA2 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.2 - Occurrence 2 Only'],
        ]);

        // Associate ticket A.2 only with occurrence 2
        $ticketA2->eventOccurrences()->attach($occurrence2->id);

        // Create booking for Ticket A.1
        $bookingA1 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA1->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Create booking for Ticket A.2
        $bookingA2 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA2->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Test: Booking A.1 should NOT be allowed at occurrence 2
        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA1, $occurrence2);
        $this->assertFalse($result1['is_eligible'], 'Booking A.1 should NOT be eligible for occurrence 2');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result1['errors']);

        // Test: Booking A.2 should NOT be allowed at occurrence 1
        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA2, $occurrence1);
        $this->assertFalse($result2['is_eligible'], 'Booking A.2 should NOT be eligible for occurrence 1');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result2['errors']);
    }

    /** @test */
    public function it_allows_seasonal_ticket_for_multiple_occurrences()
    {
        // Create Event A with three occurrences
        $eventA = Event::factory()->create(['name' => 'Concert Series']);

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(2),
        ]);

        $occurrence3 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(14),
            'end_at' => Carbon::now()->addDays(14)->addHours(2),
        ]);

        // Create Seasonal Ticket (valid for occurrences 1 and 2, but not 3)
        $seasonalTicket = TicketDefinition::factory()->create([
            'name' => ['en' => 'Season Pass - Concerts 1 & 2'],
        ]);

        // Associate seasonal ticket with occurrences 1 and 2
        $seasonalTicket->eventOccurrences()->attach([$occurrence1->id, $occurrence2->id]);

        // Create booking for seasonal ticket
        $seasonalBooking = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $seasonalTicket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2, // Can check in to 2 occurrences
        ]);

        // Test: Seasonal booking should be allowed at occurrence 1
        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence1);
        $this->assertTrue($result1['is_eligible'], 'Seasonal booking should be eligible for occurrence 1');
        $this->assertEmpty($result1['errors']);

        // Test: Seasonal booking should be allowed at occurrence 2
        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence2);
        $this->assertTrue($result2['is_eligible'], 'Seasonal booking should be eligible for occurrence 2');
        $this->assertEmpty($result2['errors']);

        // Test: Seasonal booking should NOT be allowed at occurrence 3 (not included in ticket)
        $result3 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence3);
        $this->assertFalse($result3['is_eligible'], 'Seasonal booking should NOT be eligible for occurrence 3');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result3['errors']);
    }

    /** @test */
    public function it_prevents_cross_event_ticket_usage()
    {
        // Create two different events
        $eventA = Event::factory()->create(['name' => 'Event A']);
        $eventB = Event::factory()->create(['name' => 'Event B']);

        $occurrenceA = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrenceB = EventOccurrence::factory()->create([
            'event_id' => $eventB->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        // Create ticket for Event A
        $ticketA = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket for Event A'],
        ]);
        $ticketA->eventOccurrences()->attach($occurrenceA->id);

        // Create booking for Event A ticket
        $bookingA = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Test: Event A booking should NOT be allowed at Event B occurrence
        $result = $this->eligibilityService->validateEligibilityForOccurrence($bookingA, $occurrenceB);
        $this->assertFalse($result['is_eligible'], 'Event A booking should NOT be eligible for Event B occurrence');
        $this->assertContains('The event occurrence does not belong to the same event as this booking', $result['errors']);
    }

    /** @test */
    public function it_validates_ticket_occurrence_relationship_with_operator_authorization()
    {
        $organizer = User::factory()->create();
        $organizer->assignRole(RoleNameEnum::ORGANIZER);

        // Create Event A with occurrence
        $eventA = Event::factory()->create([
            'name' => 'Event A',
            'organizer_id' => $organizer->id,
        ]);

        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $eventA->id,
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(2),
        ]);

        // Create ticket only for occurrence 1
        $ticket = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket for Occurrence 1 Only'],
        ]);
        $ticket->eventOccurrences()->attach($occurrence1->id);

        // Create booking
        $booking = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Test: Valid occurrence with authorized operator
        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence1, $organizer);
        $this->assertTrue($result1['is_eligible'], 'Should be eligible with valid ticket-occurrence and authorized operator');
        $this->assertEmpty($result1['errors']);

        // Test: Invalid occurrence with authorized operator (ticket validation should fail)
        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence2, $organizer);
        $this->assertFalse($result2['is_eligible'], 'Should NOT be eligible even with authorized operator if ticket is invalid for occurrence');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result2['errors']);
    }

    /** @test */
    public function it_handles_complex_seasonal_ticket_scenario()
    {
        // Create a music festival with multiple days
        $festival = Event::factory()->create(['name' => 'Music Festival 2025']);

        // Create 4 days of the festival
        $day1 = EventOccurrence::factory()->create([
            'event_id' => $festival->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(8),
        ]);

        $day2 = EventOccurrence::factory()->create([
            'event_id' => $festival->id,
            'start_at' => Carbon::now()->addDays(2),
            'end_at' => Carbon::now()->addDays(2)->addHours(8),
        ]);

        $day3 = EventOccurrence::factory()->create([
            'event_id' => $festival->id,
            'start_at' => Carbon::now()->addDays(3),
            'end_at' => Carbon::now()->addDays(3)->addHours(8),
        ]);

        $day4 = EventOccurrence::factory()->create([
            'event_id' => $festival->id,
            'start_at' => Carbon::now()->addDays(4),
            'end_at' => Carbon::now()->addDays(4)->addHours(8),
        ]);

        // Create different ticket types

        // Single day tickets
        $day1Ticket = TicketDefinition::factory()->create([
            'name' => ['en' => 'Day 1 Only'],
        ]);
        $day1Ticket->eventOccurrences()->attach($day1->id);

        // Weekend pass (days 1 & 2)
        $weekendPass = TicketDefinition::factory()->create([
            'name' => ['en' => 'Weekend Pass'],
        ]);
        $weekendPass->eventOccurrences()->attach([$day1->id, $day2->id]);

        // Full festival pass (all 4 days)
        $fullPass = TicketDefinition::factory()->create([
            'name' => ['en' => 'Full Festival Pass'],
        ]);
        $fullPass->eventOccurrences()->attach([$day1->id, $day2->id, $day3->id, $day4->id]);

        // Create bookings
        $singleDayBooking = Booking::factory()->create([
            'event_id' => $festival->id,
            'ticket_definition_id' => $day1Ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $weekendBooking = Booking::factory()->create([
            'event_id' => $festival->id,
            'ticket_definition_id' => $weekendPass->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2,
        ]);

        $fullPassBooking = Booking::factory()->create([
            'event_id' => $festival->id,
            'ticket_definition_id' => $fullPass->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 4,
        ]);

        // Test single day ticket
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($singleDayBooking, $day1)['is_eligible']);
        $this->assertFalse($this->eligibilityService->validateEligibilityForOccurrence($singleDayBooking, $day2)['is_eligible']);
        $this->assertFalse($this->eligibilityService->validateEligibilityForOccurrence($singleDayBooking, $day3)['is_eligible']);
        $this->assertFalse($this->eligibilityService->validateEligibilityForOccurrence($singleDayBooking, $day4)['is_eligible']);

        // Test weekend pass
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($weekendBooking, $day1)['is_eligible']);
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($weekendBooking, $day2)['is_eligible']);
        $this->assertFalse($this->eligibilityService->validateEligibilityForOccurrence($weekendBooking, $day3)['is_eligible']);
        $this->assertFalse($this->eligibilityService->validateEligibilityForOccurrence($weekendBooking, $day4)['is_eligible']);

        // Test full festival pass
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($fullPassBooking, $day1)['is_eligible']);
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($fullPassBooking, $day2)['is_eligible']);
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($fullPassBooking, $day3)['is_eligible']);
        $this->assertTrue($this->eligibilityService->validateEligibilityForOccurrence($fullPassBooking, $day4)['is_eligible']);
    }
}
