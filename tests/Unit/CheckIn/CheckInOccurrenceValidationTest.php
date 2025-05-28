<?php

namespace Tests\Unit\CheckIn;

use App\Enums\CheckInStatus;
use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Services\CheckInEligibilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckInOccurrenceValidationTest extends TestCase
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
    public function it_allows_check_in_for_booking_at_correct_event_occurrence()
    {
        $event = Event::factory()->create();
        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        // Create a ticket and associate it with the occurrence
        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($eventOccurrence->id, $result['event_occurrence']->id);
    }

    /** @test */
    public function it_rejects_check_in_for_booking_at_wrong_event_occurrence()
    {
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event2->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        $booking = Booking::factory()->create([
            'event_id' => $event1->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('The event occurrence does not belong to the same event as this booking', $result['errors']);
    }

    /** @test */
    public function it_provides_detailed_occurrence_timing_information()
    {
        $event = Event::factory()->create(['name' => 'Test Event']);
        $startTime = Carbon::now()->addHour();
        $endTime = Carbon::now()->addHours(3);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => $startTime,
            'end_at' => $endTime,
        ]);

        // Create a ticket and associate it with the occurrence
        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence);

        $this->assertTrue($result['is_eligible']);
        $this->assertArrayHasKey('timing_info', $result);
        $this->assertArrayHasKey('event_id', $result['timing_info']);
        $this->assertArrayHasKey('event_name', $result['timing_info']);
        $this->assertArrayHasKey('occurrence_id', $result['timing_info']);
        $this->assertArrayHasKey('occurrence_starts_at', $result['timing_info']);
        $this->assertArrayHasKey('occurrence_ends_at', $result['timing_info']);
        $this->assertArrayHasKey('current_time', $result['timing_info']);

        $this->assertEquals($event->id, $result['timing_info']['event_id']);
        $this->assertEquals('Test Event', $result['timing_info']['event_name']);
        $this->assertEquals($eventOccurrence->id, $result['timing_info']['occurrence_id']);
        // Use assertStringStartsWith to avoid precision issues with timestamps
        $this->assertStringStartsWith($startTime->format('Y-m-d\TH:i:s'), $result['timing_info']['occurrence_starts_at']);
        $this->assertStringStartsWith($endTime->format('Y-m-d\TH:i:s'), $result['timing_info']['occurrence_ends_at']);
    }

    /** @test */
    public function it_validates_operator_authorization_for_occurrence_check_in()
    {
        $organizer = User::factory()->create();
        $organizer->assignRole(RoleNameEnum::ORGANIZER);

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        // Create a ticket and associate it with the occurrence
        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence, $organizer);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_rejects_occurrence_check_in_for_wrong_organizer()
    {
        $organizer1 = User::factory()->create();
        $organizer1->assignRole(RoleNameEnum::ORGANIZER);

        $organizer2 = User::factory()->create();
        $organizer2->assignRole(RoleNameEnum::ORGANIZER);

        $event = Event::factory()->create([
            'organizer_id' => $organizer1->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        // Create a ticket and associate it with the occurrence
        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence, $organizer2);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('The operator must be the organizer of this specific event or a platform admin', $result['errors']);
    }

    /** @test */
    public function it_handles_multiple_validation_errors_for_occurrence()
    {
        $event1 = Event::factory()->create();
        $event2 = Event::factory()->create();

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event2->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        $booking = Booking::factory()->create([
            'event_id' => $event1->id,
            'status' => 'cancelled',
            'max_allowed_check_ins' => 1,
        ]);

        // Create a successful check-in to reach max limit
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence);

        $this->assertFalse($result['is_eligible']);
        $this->assertCount(4, $result['errors']); // Now includes ticket-occurrence validation error
        $this->assertContains('The event occurrence does not belong to the same event as this booking', $result['errors']);
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result['errors']);
        $this->assertContains('Booking status is not valid for check-in (current status: cancelled)', $result['errors']);
        $this->assertContains('Maximum allowed check-ins reached (1/1)', $result['errors']);
    }

    /** @test */
    public function it_supports_seasonal_tickets_across_multiple_occurrences()
    {
        $event = Event::factory()->create(['name' => 'Concert Series']);

        // Create multiple occurrences for the same event (seasonal ticket scenario)
        $occurrence1 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addDays(1),
            'end_at' => Carbon::now()->addDays(1)->addHours(2),
        ]);

        $occurrence2 = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(2),
        ]);

        // Create a seasonal ticket that works for both occurrences
        $seasonalTicket = TicketDefinition::factory()->create();
        $seasonalTicket->eventOccurrences()->attach([$occurrence1->id, $occurrence2->id]);

        // Booking is for the event (not specific occurrence) - seasonal ticket
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $seasonalTicket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2, // Can check in to multiple occurrences
        ]);

        // Should be able to check in to first occurrence
        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence1);
        $this->assertTrue($result1['is_eligible']);
        $this->assertEmpty($result1['errors']);

        // Should be able to check in to second occurrence
        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence2);
        $this->assertTrue($result2['is_eligible']);
        $this->assertEmpty($result2['errors']);
    }
}
