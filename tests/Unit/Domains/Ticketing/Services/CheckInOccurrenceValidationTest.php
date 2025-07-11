<?php

namespace Tests\Unit\Domains\Ticketing\Services;

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

        Role::create(['name' => RoleNameEnum::ADMIN->value]);
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
        $this->assertStringStartsWith($startTime->format('Y-m-d\TH:i:s'), $result['timing_info']['occurrence_starts_at']);
        $this->assertStringStartsWith($endTime->format('Y-m-d\TH:i:s'), $result['timing_info']['occurrence_ends_at']);
    }

    /** @test */
    public function it_validates_operator_authorization_for_occurrence_check_in()
    {
        // Create organizer entity and user
        $organizer = \App\Models\Organizer::factory()->create();
        $organizerUser = User::factory()->create();

        // Create organizer-user relationship
        $organizer->users()->attach($organizerUser->id, [
            'role_in_organizer' => \App\Enums\OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence, $organizerUser);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_rejects_occurrence_check_in_for_wrong_organizer()
    {
        // Create two separate organizers with users
        $organizer1 = \App\Models\Organizer::factory()->create();
        $organizerUser1 = User::factory()->create();
        $organizer1->users()->attach($organizerUser1->id, [
            'role_in_organizer' => \App\Enums\OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $organizer2 = \App\Models\Organizer::factory()->create();
        $organizerUser2 = User::factory()->create();
        $organizer2->users()->attach($organizerUser2->id, [
            'role_in_organizer' => \App\Enums\OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Create event for organizer1
        $event = Event::factory()->create([
            'organizer_id' => $organizer1->id,
        ]);

        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'start_at' => Carbon::now()->addHour(),
            'end_at' => Carbon::now()->addHours(3),
        ]);

        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Try to check in with organizer2's user (should fail)
        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence, $organizerUser2);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('You are not authorized to check in for this event.', $result['errors']);
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

        $ticket = TicketDefinition::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event1->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'cancelled',
            'max_allowed_check_ins' => 1,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $result = $this->eligibilityService->validateEligibilityForOccurrence($booking, $eventOccurrence);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('The event occurrence does not belong to the same event as this booking', $result['errors']);
        $this->assertContains('This ticket is not valid for the selected event occurrence', $result['errors']);
        $this->assertContains('Booking status is not valid for check-in (current status: cancelled)', $result['errors']);
        $this->assertContains('Maximum allowed check-ins reached (1/1)', $result['errors']);
        $this->assertCount(4, $result['errors']);
    }

    /** @test */
    public function it_supports_seasonal_tickets_across_multiple_occurrences()
    {
        $event = Event::factory()->create(['name' => 'Concert Series']);

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

        $seasonalTicket = TicketDefinition::factory()->create();
        $seasonalTicket->eventOccurrences()->attach([$occurrence1->id, $occurrence2->id]);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $seasonalTicket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2,
        ]);

        $result1 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence1);
        $this->assertTrue($result1['is_eligible'], "Failed for occurrence 1: " . implode(", ", $result1['errors'] ?? []));
        CheckInLog::factory()->create(['booking_id' => $booking->id, 'status' => CheckInStatus::SUCCESSFUL, 'event_occurrence_id' => $occurrence1->id]);

        $result2 = $this->eligibilityService->validateEligibilityForOccurrence($booking, $occurrence2);
        $this->assertTrue($result2['is_eligible'], "Failed for occurrence 2: " . implode(", ", $result2['errors'] ?? []));
    }
}
