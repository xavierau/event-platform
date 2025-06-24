<?php

namespace Tests\Unit\CheckIn;

use App\Enums\BookingStatusEnum;
use App\Enums\CheckInStatus;
use App\Enums\RoleNameEnum;
use App\Enums\OrganizerRoleEnum;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Services\CheckInEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CheckInEligibilityValidationTest extends TestCase
{
    use RefreshDatabase;

    private CheckInEligibilityService $eligibilityService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibilityService = new CheckInEligibilityService();

        // Create only the admin role for testing
        Role::create(['name' => RoleNameEnum::ADMIN->value]);
    }

    /** @test */
    public function it_allows_check_in_for_confirmed_booking()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals($booking->id, $result['booking']->id);
    }

    /** @test */
    public function it_rejects_check_in_for_cancelled_booking()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'cancelled',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('Booking status is not valid for check-in (current status: cancelled)', $result['errors']);
    }

    /** @test */
    public function it_rejects_check_in_when_max_check_ins_reached()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Create a successful check-in log
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('Maximum allowed check-ins reached (1/1)', $result['errors']);
    }

    /** @test */
    public function it_allows_check_in_when_under_max_limit()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 3,
        ]);

        // Create 2 successful check-in logs (under the limit of 3)
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_handles_multiple_validation_errors()
    {
        // Booking cancelled, max check-ins reached
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'cancelled',
            'max_allowed_check_ins' => 1,
        ]);

        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertFalse($result['is_eligible']);
        $this->assertCount(2, $result['errors']);
        $this->assertContains('Booking status is not valid for check-in (current status: cancelled)', $result['errors']);
        $this->assertContains('Maximum allowed check-ins reached (1/1)', $result['errors']);
    }

    /** @test */
    public function it_ignores_failed_check_in_attempts_in_count()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 2,
        ]);

        // Create 1 successful and 2 failed check-in logs
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::SUCCESSFUL,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::FAILED_INVALID_CODE,
        ]);
        CheckInLog::factory()->create([
            'booking_id' => $booking->id,
            'status' => CheckInStatus::FAILED_ALREADY_USED,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
        $this->assertEquals(1, $result['check_in_count']);
    }

    /** @test */
    public function it_provides_detailed_timing_information()
    {
        $event = Event::factory()->create(['name' => 'Test Event']);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertTrue($result['is_eligible']);
        $this->assertArrayHasKey('timing_info', $result);
        $this->assertArrayHasKey('event_id', $result['timing_info']);
        $this->assertArrayHasKey('event_name', $result['timing_info']);
        $this->assertArrayHasKey('current_time', $result['timing_info']);
        $this->assertEquals($event->id, $result['timing_info']['event_id']);
        $this->assertEquals('Test Event', $result['timing_info']['event_name']);
    }

    /** @test */
    public function it_allows_check_in_for_platform_admin()
    {
        $platformAdmin = User::factory()->create();
        $platformAdmin->assignRole(RoleNameEnum::ADMIN);

        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking, $platformAdmin);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_allows_check_in_for_event_organizer()
    {
        // Create organizer entity and user
        $organizer = Organizer::factory()->create();
        $organizerUser = User::factory()->create();

        // Create organizer-user relationship
        $organizer->users()->attach($organizerUser->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $event = Event::factory()->create([
            'organizer_id' => $organizer->id,
        ]);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking, $organizerUser);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_rejects_check_in_for_wrong_organizer()
    {
        // Create two separate organizers with users
        $organizer1 = Organizer::factory()->create();
        $organizerUser1 = User::factory()->create();
        $organizer1->users()->attach($organizerUser1->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        $organizer2 = Organizer::factory()->create();
        $organizerUser2 = User::factory()->create();
        $organizer2->users()->attach($organizerUser2->id, [
            'role_in_organizer' => OrganizerRoleEnum::MANAGER->value,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        // Create event for organizer1
        $event = Event::factory()->create([
            'organizer_id' => $organizer1->id,
        ]);

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        // Try to check in with organizer2's user (should fail)
        $result = $this->eligibilityService->validateEligibility($booking, $organizerUser2);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('You are not authorized to check in for this event.', $result['errors']);
    }

    /** @test */
    public function it_rejects_check_in_for_user_without_proper_role()
    {
        $regularUser = User::factory()->create();

        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking, $regularUser);

        $this->assertFalse($result['is_eligible']);
        $this->assertContains('You are not authorized to check in for this event.', $result['errors']);
    }

    /** @test */
    public function it_allows_check_in_without_operator_for_backward_compatibility()
    {
        $event = Event::factory()->create();

        $booking = Booking::factory()->create([
            'event_id' => $event->id,
            'status' => 'confirmed',
            'max_allowed_check_ins' => 1,
        ]);

        $result = $this->eligibilityService->validateEligibility($booking);

        $this->assertTrue($result['is_eligible']);
        $this->assertEmpty($result['errors']);
    }
}
