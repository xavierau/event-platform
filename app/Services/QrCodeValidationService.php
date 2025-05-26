<?php

namespace App\Services;

use App\Helpers\QrCodeHelper;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;

class QrCodeValidationService
{
    /**
     * Validate QR code format.
     * Expected format: BK-XXXXXXXXXXXX (BK- prefix followed by 12 alphanumeric characters)
     */
    public function isValidFormat(string $qrCode): bool
    {
        return QrCodeHelper::isValidFormat($qrCode);
    }

    /**
     * Find booking by QR code identifier.
     * Returns null if QR code format is invalid or booking not found.
     */
    public function findBookingByQrCode(string $qrCode): ?Booking
    {
        // First validate the format
        if (!$this->isValidFormat($qrCode)) {
            return null;
        }

        // Find booking with relationships loaded
        return Booking::with(['event', 'user', 'transaction'])
            ->byQrCode($qrCode)
            ->first();
    }

    /**
     * Comprehensive QR code validation.
     * Returns array with validation result, booking (if found), and any errors.
     */
    public function validateQrCode(string $qrCode): array
    {
        $errors = [];
        $booking = null;

        // Step 1: Validate QR code format
        if (!$this->isValidFormat($qrCode)) {
            $errors[] = 'Invalid QR code format';
            return [
                'is_valid' => false,
                'booking' => null,
                'errors' => $errors,
            ];
        }

        // Step 2: Find booking by QR code
        $booking = $this->findBookingByQrCode($qrCode);
        if (!$booking) {
            $errors[] = 'Booking not found for this QR code';
            return [
                'is_valid' => false,
                'booking' => null,
                'errors' => $errors,
            ];
        }

        // Step 3: Basic booking validation (more detailed validation will be in CHK-002.4)
        if (!$booking->event) {
            $errors[] = 'Event not found for this booking';
        }

        if (!$booking->user) {
            $errors[] = 'User not found for this booking';
        }

        // Log successful QR code validation for audit purposes
        if (empty($errors)) {
            Log::info('QR code validation successful', [
                'qr_code' => $qrCode,
                'booking_id' => $booking->id,
                'user_id' => $booking->user->id ?? null,
                'event_id' => $booking->event->id ?? null,
            ]);
        } else {
            Log::warning('QR code validation failed', [
                'qr_code' => $qrCode,
                'errors' => $errors,
                'booking_id' => $booking->id ?? null,
            ]);
        }

        return [
            'is_valid' => empty($errors),
            'booking' => $booking,
            'errors' => $errors,
        ];
    }
}
