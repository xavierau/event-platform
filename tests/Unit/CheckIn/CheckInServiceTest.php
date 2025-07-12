<?php

namespace Tests\Unit\CheckIn;

use App\DataTransferObjects\CheckInData;
use App\Enums\CheckInMethod;
use App\Enums\CheckInStatus;
use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\User;
use App\Services\CheckInEligibilityService;
use App\Services\CheckInService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private CheckInService $checkInService;
    private CheckInEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles for testing
        Role::create(['name' => RoleNameEnum::ADMIN->value]);
        Role::create(['name' => RoleNameEnum::USER->value]);

        $this->eligibilityService = new CheckInEligibilityService();
        $this->checkInService = new CheckInService($this->eligibilityService);
    }

    /** @test */
    public function it_successfully_processes_check_in_for_valid_booking()
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
            'max_allowed_check_ins' => 2,
        ]);

        $operator = User::factory()->create();
        $operator->assignRole(RoleNameEnum::ADMIN);

        $checkInData = new CheckInData(
            qr_code_identifier: $booking->qr_code_identifier,
            event_occurrence_id: $eventOccurrence->id,
            method: CheckInMethod::QR_SCAN,
            device_identifier: 'test-device-123',
            location_description: 'Main Entrance',
            operator_user_id: $operator->id,
            notes: 'Test check-in'
        );

        $result = $this->checkInService->processCheckIn($checkInData);

        $this->assertTrue($result['success']);
        $this->assertEquals('Check-in successful', $result['message']);
        $this->assertInstanceOf(CheckInLog::class, $result['check_in_log']);
        $this->assertEquals(CheckInStatus::SUCCESSFUL, $result['check_in_log']->status);
        $this->assertEquals(1, $result['remaining_check_ins']);

        // Verify booking status was updated
        $booking->refresh();
        $this->assertEquals('used', $booking->status->value);

        // Verify check-in log was created
        $this->assertDatabaseHas('check_in_logs', [
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::SUCCESSFUL->value,
            'method' => CheckInMethod::QR_SCAN->value,
            'device_identifier' => 'test-device-123',
            'location_description' => 'Main Entrance',
            'operator_user_id' => $operator->id,
            'notes' => 'Test check-in',
        ]);
    }

    /** @test */
    public function it_fails_check_in_for_invalid_booking()
    {
        $eventOccurrence = EventOccurrence::factory()->create();

        $checkInData = new CheckInData(
            qr_code_identifier: 'invalid-qr-code',
            event_occurrence_id: $eventOccurrence->id,
            method: CheckInMethod::QR_SCAN,
            device_identifier: null,
            location_description: null,
            operator_user_id: null,
            notes: null
        );

        $result = $this->checkInService->processCheckIn($checkInData);

        $this->assertFalse($result['success']);
        $this->assertEquals('Booking not found', $result['message']);
        $this->assertEquals(CheckInStatus::FAILED_INVALID_CODE, $result['status']);
    }

    /** @test */
    public function it_fails_check_in_when_max_uses_reached()
    {
        $event = Event::factory()->create();
        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);

        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Create a successful check-in log to reach the limit
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $checkInData = new CheckInData(
            qr_code_identifier: $booking->qr_code_identifier,
            event_occurrence_id: $eventOccurrence->id,
            method: CheckInMethod::QR_SCAN,
            device_identifier: null,
            location_description: null,
            operator_user_id: null,
            notes: null
        );

        $result = $this->checkInService->processCheckIn($checkInData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Maximum allowed check-ins reached', $result['message']);
        $this->assertEquals(CheckInStatus::FAILED_MAX_USES_REACHED, $result['status']);
    }

    /** @test */
    public function it_does_not_update_booking_status_for_subsequent_check_ins()
    {
        $event = Event::factory()->create();
        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);

        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 3,
        ]);

        // First check-in
        $checkInData = new CheckInData(
            qr_code_identifier: $booking->qr_code_identifier,
            event_occurrence_id: $eventOccurrence->id,
            method: CheckInMethod::QR_SCAN,
            device_identifier: null,
            location_description: null,
            operator_user_id: null,
            notes: null
        );

        $result1 = $this->checkInService->processCheckIn($checkInData);
        $this->assertTrue($result1['success']);

        $booking->refresh();
        $this->assertEquals('used', $booking->status->value);

        // Second check-in (should not change status again)
        $result2 = $this->checkInService->processCheckIn($checkInData);
        $this->assertTrue($result2['success']);

        $booking->refresh();
        $this->assertEquals('used', $booking->status->value);
    }

    /** @test */
    public function it_returns_detailed_check_in_history()
    {
        $event = Event::factory()->create(['name' => 'Test Event']);
        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
            'name' => 'Main Session',
        ]);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'max_allowed_check_ins' => 3,
        ]);

        $operator = User::factory()->create(['name' => 'Test Operator']);

        // Create some check-in logs
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
            'operator_user_id' => $operator->id,
            'check_in_timestamp' => Carbon::now()->subHours(2),
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::FAILED_INVALID_CODE,
            'operator_user_id' => $operator->id,
            'check_in_timestamp' => Carbon::now()->subHour(),
        ]);

        $history = $this->checkInService->getCheckInHistory($booking);

        $this->assertEquals(2, $history['total_check_ins']);
        $this->assertEquals(1, $history['successful_check_ins']);
        $this->assertEquals(1, $history['failed_check_ins']);
        $this->assertEquals(2, $history['remaining_check_ins']);
        $this->assertEquals(3, $history['max_allowed_check_ins']);

        $this->assertCount(2, $history['check_in_logs']);

        // Check that logs are ordered by timestamp desc (most recent first)
        $firstLog = $history['check_in_logs'][0];
        $this->assertEquals(CheckInStatus::FAILED_INVALID_CODE->value, $firstLog['status']);
        $this->assertEquals('Main Session', $firstLog['event_occurrence']['name']);
        $this->assertEquals('Test Operator', $firstLog['operator']['name']);
    }

    /** @test */
    public function it_validates_check_in_eligibility_correctly()
    {
        $event = Event::factory()->create();
        $eventOccurrence = EventOccurrence::factory()->create([
            'event_id' => $event->id,
        ]);

        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $operator = User::factory()->create();
        $operator->assignRole(RoleNameEnum::ADMIN);

        $result = $this->checkInService->validateCheckInEligibility(
            $booking->qr_code_identifier,
            $eventOccurrence->id,
            $operator
        );

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($booking->id, $result['booking']->id);
        $this->assertEquals($eventOccurrence->id, $result['event_occurrence']->id);
    }

    /** @test */
    public function it_handles_invalid_qr_code_in_eligibility_check()
    {
        $eventOccurrence = EventOccurrence::factory()->create();

        $result = $this->checkInService->validateCheckInEligibility(
            'invalid-qr-code',
            $eventOccurrence->id
        );

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('Booking not found', $result['errors']);
        $this->assertNull($result['booking']);
    }

    /** @test */
    public function it_handles_invalid_event_occurrence_in_eligibility_check()
    {
        $booking = Booking::factory()->create([
            'status' => 'confirmed',
        ]);

        $result = $this->checkInService->validateCheckInEligibility(
            $booking->qr_code_identifier,
            999999 // Non-existent occurrence ID
        );

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('Event occurrence not found', $result['errors']);
        $this->assertEquals($booking->id, $result['booking']->id);
        $this->assertNull($result['event_occurrence']);
    }
}
