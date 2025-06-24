<?php

namespace Tests\Unit\Domains\Ticketing\Services;

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

        $booking->refresh();
        $this->assertEquals('used', $booking->status->value);

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

        $log1_time = Carbon::now()->subHours(2);
        $log2_time = Carbon::now()->subHour();

        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::SUCCESSFUL,
            'operator_user_id' => $operator->id,
            'check_in_timestamp' => $log1_time,
            'method' => CheckInMethod::MANUAL_ENTRY,
            'device_identifier' => 'console',
            'location_description' => 'VIP Desk'
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'status' => CheckInStatus::FAILED_INVALID_CODE,
            'operator_user_id' => $operator->id,
            'check_in_timestamp' => $log2_time,
            'method' => CheckInMethod::QR_SCAN,
            'device_identifier' => 'scanner-01',
            'location_description' => 'Gate A'
        ]);

        $history = $this->checkInService->getCheckInHistory($booking);

        $this->assertEquals(2, $history['total_check_ins']);
        $this->assertEquals(1, $history['successful_check_ins']);
        $this->assertEquals(1, $history['failed_check_ins']);
        $this->assertEquals(3 - 1, $history['remaining_check_ins']);
        $this->assertEquals(3, $history['max_allowed_check_ins']);

        $this->assertCount(2, $history['check_in_logs']);

        $this->assertEquals($log2_time->toIso8601String(), Carbon::parse($history['check_in_logs'][0]['timestamp'])->toIso8601String());
        $this->assertEquals($log1_time->toIso8601String(), Carbon::parse($history['check_in_logs'][1]['timestamp'])->toIso8601String());

        $this->assertEquals('Test Operator', $history['check_in_logs'][0]['operator']['name']);
        $this->assertEquals('Main Session', $history['check_in_logs'][0]['event_occurrence']['name']);
        $this->assertEquals(CheckInStatus::FAILED_INVALID_CODE->value, $history['check_in_logs'][0]['status']);
    }

    /** @test */
    public function it_validates_check_in_eligibility_correctly()
    {
        $event = Event::factory()->create();
        $eventOccurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);
        $ticket = TicketDefinition::factory()->create();
        $ticket->eventOccurrences()->attach($eventOccurrence->id);
        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'ticket_definition_id' => $ticket->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $eligibility = $this->checkInService->validateCheckInEligibility(
            $booking->qr_code_identifier,
            $eventOccurrence->id
        );

        $this->assertTrue($eligibility['is_eligible']);
        $this->assertEmpty($eligibility['errors']);
    }

    /** @test */
    public function it_handles_invalid_qr_code_in_eligibility_check()
    {
        $eventOccurrence = EventOccurrence::factory()->create();
        $eligibility = $this->checkInService->validateCheckInEligibility(
            'non-existent-qr',
            $eventOccurrence->id
        );

        $this->assertFalse($eligibility['is_eligible']);
        $this->assertContains('Booking not found', $eligibility['errors']);
    }

    /** @test */
    public function it_handles_invalid_event_occurrence_in_eligibility_check()
    {
        $booking = Booking::factory()->create();
        $eligibility = $this->checkInService->validateCheckInEligibility(
            $booking->qr_code_identifier,
            9999
        );

        $this->assertFalse($eligibility['is_eligible']);
        $this->assertContains('Event occurrence not found', $eligibility['errors']);
    }
}
