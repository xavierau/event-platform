<?php

namespace Tests\Unit\Domains\Ticketing\Services;

use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
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

        Role::create(['name' => RoleNameEnum::ADMIN->value]);
        Role::create(['name' => RoleNameEnum::ORGANIZER->value]);
    }

    /** @test */
    public function it_allows_check_in_for_ticket_at_its_designated_occurrence()
    {
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

        $ticketA1 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.1 - Occurrence 1 Only'],
        ]);
        $ticketA1->eventOccurrences()->attach($occurrence1->id);

        $ticketA2 = TicketDefinition::factory()->create([
            'name' => ['en' => 'Ticket A.2 - Occurrence 2 Only'],
        ]);
        $ticketA2->eventOccurrences()->attach($occurrence2->id);

        $bookingA1 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA1->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $bookingA2 = Booking::factory()->create([
            'event_id' => $eventA->id,
            'ticket_definition_id' => $ticketA2->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA1, $occurrence1);
        $this->assertTrue($result1['is_eligible'], 'Booking A.1 should be eligible for occurrence 1. Errors: ' . implode(', ', $result1['errors'] ?? []));
        $this->assertEmpty($result1['errors']);

        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA2, $occurrence2);
        $this->assertTrue($result2['is_eligible'], 'Booking A.2 should be eligible for occurrence 2. Errors: ' . implode(', ', $result2['errors'] ?? []));
        $this->assertEmpty($result2['errors']);
    }

    /** @test */
    public function it_rejects_check_in_for_ticket_at_wrong_occurrence()
    {
        $eventA = Event::factory()->create(['name' => 'Event A']);
        $occurrence1 = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(1), 'end_at' => Carbon::now()->addDays(1)->addHours(2)]);
        $occurrence2 = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(7), 'end_at' => Carbon::now()->addDays(7)->addHours(2)]);

        $ticketA1 = TicketDefinition::factory()->create(['name' => ['en' => 'Ticket A.1 - Occurrence 1 Only']]);
        $ticketA1->eventOccurrences()->attach($occurrence1->id);

        $ticketA2 = TicketDefinition::factory()->create(['name' => ['en' => 'Ticket A.2 - Occurrence 2 Only']]);
        $ticketA2->eventOccurrences()->attach($occurrence2->id);

        $bookingA1 = Booking::factory()->create(['event_id' => $eventA->id, 'ticket_definition_id' => $ticketA1->id, 'status' => 'confirmed', 'max_allowed_check_ins' => 1]);
        $bookingA2 = Booking::factory()->create(['event_id' => $eventA->id, 'ticket_definition_id' => $ticketA2->id, 'status' => 'confirmed', 'max_allowed_check_ins' => 1]);

        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA1, $occurrence2);
        $this->assertFalse($result1['is_eligible'], 'Booking A.1 should NOT be eligible for occurrence 2');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result1['errors']);

        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($bookingA2, $occurrence1);
        $this->assertFalse($result2['is_eligible'], 'Booking A.2 should NOT be eligible for occurrence 1');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result2['errors']);
    }

    /** @test */
    public function it_allows_seasonal_ticket_for_multiple_occurrences()
    {
        $eventA = Event::factory()->create(['name' => 'Concert Series']);
        $occurrence1 = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(1), 'end_at' => Carbon::now()->addDays(1)->addHours(2)]);
        $occurrence2 = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(7), 'end_at' => Carbon::now()->addDays(7)->addHours(2)]);
        $occurrence3 = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(14), 'end_at' => Carbon::now()->addDays(14)->addHours(2)]);

        $seasonalTicket = TicketDefinition::factory()->create(['name' => ['en' => 'Season Pass - Concerts 1 & 2']]);
        $seasonalTicket->eventOccurrences()->attach([$occurrence1->id, $occurrence2->id]);

        $seasonalBooking = Booking::factory()->create(['event_id' => $eventA->id, 'ticket_definition_id' => $seasonalTicket->id, 'status' => 'confirmed', 'max_allowed_check_ins' => 2]);

        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence1);
        $this->assertTrue($result1['is_eligible'], 'Seasonal booking should be eligible for occurrence 1. Errors: ' . implode(', ', $result1['errors'] ?? []));
        $this->assertEmpty($result1['errors']);

        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence2);
        $this->assertTrue($result2['is_eligible'], 'Seasonal booking should be eligible for occurrence 2. Errors: ' . implode(', ', $result2['errors'] ?? []));
        $this->assertEmpty($result2['errors']);

        $result3 = $this->eligibilityService->validateEligibilityForOccurrence($seasonalBooking, $occurrence3);
        $this->assertFalse($result3['is_eligible'], 'Seasonal booking should NOT be eligible for occurrence 3');
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result3['errors']);
    }

    /** @test */
    public function it_prevents_cross_event_ticket_usage()
    {
        $eventA = Event::factory()->create(['name' => 'Event A']);
        $eventB = Event::factory()->create(['name' => 'Event B']);

        $occurrenceA = EventOccurrence::factory()->create(['event_id' => $eventA->id, 'start_at' => Carbon::now()->addDays(1), 'end_at' => Carbon::now()->addDays(1)->addHours(2)]);
        $occurrenceB = EventOccurrence::factory()->create(['event_id' => $eventB->id, 'start_at' => Carbon::now()->addDays(1), 'end_at' => Carbon::now()->addDays(1)->addHours(2)]);

        $ticketA = TicketDefinition::factory()->create(['name' => ['en' => 'Ticket for Event A']]);
        $ticketA->eventOccurrences()->attach($occurrenceA->id);

        $bookingA = Booking::factory()->create(['event_id' => $eventA->id, 'ticket_definition_id' => $ticketA->id, 'status' => 'confirmed', 'max_allowed_check_ins' => 1]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($bookingA, $occurrenceB);
        $this->assertFalse($result['is_eligible'], 'Event A booking should NOT be eligible for Event B occurrence');
        $this->assertContains('The event occurrence does not belong to the same event as this booking', $result['errors']);
    }
}
