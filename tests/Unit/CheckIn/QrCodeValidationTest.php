<?php

namespace Tests\Unit\CheckIn;

use App\Models\Booking;
use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\TicketDefinition;
use App\Models\Transaction;
use App\Models\User;
use App\Services\QrCodeValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrCodeValidationTest extends TestCase
{
    use RefreshDatabase;

    private QrCodeValidationService $qrCodeValidationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->qrCodeValidationService = new QrCodeValidationService();
    }

    /** @test */
    public function it_validates_qr_code_format_correctly()
    {
        // Valid QR code format (BK-XXXXXXXXXXXX)
        $this->assertTrue($this->qrCodeValidationService->isValidFormat('BK-ABC123DEF456'));
        $this->assertTrue($this->qrCodeValidationService->isValidFormat('BK-123456789012'));

        // Invalid formats
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('ABC123DEF456')); // Missing BK- prefix
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('BK-ABC123')); // Too short
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('BK-ABC123DEF4567')); // Too long
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('bk-abc123def456')); // Lowercase
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('')); // Empty
        $this->assertFalse($this->qrCodeValidationService->isValidFormat('BK-ABC123-DEF456')); // Extra dash
    }

    /** @test */
    public function it_finds_booking_by_valid_qr_code()
    {
        // Create test data
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'qr_code_identifier' => 'BK-TEST12345678',
        ]);

        $result = $this->qrCodeValidationService->findBookingByQrCode('BK-TEST12345678');

        $this->assertNotNull($result);
        $this->assertEquals($booking->id, $result->id);
        $this->assertEquals('BK-TEST12345678', $result->qr_code_identifier);
    }

    /** @test */
    public function it_returns_null_for_non_existent_qr_code()
    {
        $result = $this->qrCodeValidationService->findBookingByQrCode('BK-NONEXISTENT123');

        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_null_for_invalid_qr_code_format()
    {
        $result = $this->qrCodeValidationService->findBookingByQrCode('INVALID-FORMAT');

        $this->assertNull($result);
    }

    /** @test */
    public function it_validates_booking_exists_and_is_valid()
    {
        // Create test data
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $validBooking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'qr_code_identifier' => 'BK-VALID1234567',
            'status' => 'confirmed',
        ]);

        $result = $this->qrCodeValidationService->validateQrCode('BK-VALID1234567');

        $this->assertTrue($result['is_valid']);
        $this->assertEquals($validBooking->id, $result['booking']->id);
        $this->assertEmpty($result['errors']);
    }

    /** @test */
    public function it_returns_validation_errors_for_invalid_qr_code()
    {
        $result = $this->qrCodeValidationService->validateQrCode('INVALID-FORMAT');

        $this->assertFalse($result['is_valid']);
        $this->assertNull($result['booking']);
        $this->assertContains('Booking not found for this QR code', $result['errors']);
    }

    /** @test */
    public function it_returns_validation_errors_for_non_existent_booking()
    {
        $result = $this->qrCodeValidationService->validateQrCode('BK-NONEXIST1234');

        $this->assertFalse($result['is_valid']);
        $this->assertNull($result['booking']);
        $this->assertContains('Booking not found for this QR code', $result['errors']);
    }

    /** @test */
    public function it_validates_qr_code_with_booking_relationships()
    {
        // Create test data with relationships
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'qr_code_identifier' => 'BK-WITHRELS1234',
            'status' => 'confirmed',
        ]);

        $result = $this->qrCodeValidationService->validateQrCode('BK-WITHRELS1234');

        $this->assertTrue($result['is_valid']);
        $this->assertNotNull($result['booking']);
        $this->assertNotNull($result['booking']->event);
        $this->assertNotNull($result['booking']->user);
        $this->assertEquals($event->id, $result['booking']->event->id);
        $this->assertEquals($user->id, $result['booking']->user->id);
    }

    /** @test */
    public function it_finds_booking_by_booking_number_uuid_format()
    {
        // Create test data
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $bookingNumber = '550e8400-e29b-41d4-a716-446655440000'; // Example UUID
        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'booking_number' => $bookingNumber,
            'qr_code_identifier' => 'BK-LEGACY123456', // Has BK- format too
            'status' => 'confirmed',
        ]);

        // Test finding by booking_number (UUID format)
        $result = $this->qrCodeValidationService->findBookingByQrCode($bookingNumber);

        $this->assertNotNull($result);
        $this->assertEquals($booking->id, $result->id);
        $this->assertEquals($bookingNumber, $result->booking_number);
    }

    /** @test */
    public function it_validates_qr_code_with_booking_number_uuid_format()
    {
        // Create test data
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $ticketDefinition = TicketDefinition::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $bookingNumber = '550e8400-e29b-41d4-a716-446655440001'; // Example UUID
        $booking = Booking::factory()->create([
            'transaction_id' => $transaction->id,
            'event_id' => $event->id,
            'ticket_definition_id' => $ticketDefinition->id,
            'booking_number' => $bookingNumber,
            'status' => 'confirmed',
        ]);

        // Test validation with booking_number (UUID format)
        $result = $this->qrCodeValidationService->validateQrCode($bookingNumber);

        $this->assertTrue($result['is_valid']);
        $this->assertEquals($booking->id, $result['booking']->id);
        $this->assertEmpty($result['errors']);
    }
}
