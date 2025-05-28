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
     * Supports both qr_code_identifier (BK-XXXXXXXXXXXX) and booking_number (UUID) formats.
     */
    public function findBookingByQrCode(string $qrCode): ?Booking
    {
        // First try to find by qr_code_identifier (BK- format)
        if ($this->isValidFormat($qrCode)) {
            return Booking::with(['event', 'user', 'transaction'])
                ->byQrCode($qrCode)
                ->first();
        }

        // If not BK- format, try to find by booking_number (UUID format)
        // This supports legacy QR codes that contain booking numbers
        return Booking::with(['event', 'user', 'transaction'])
            ->where('booking_number', $qrCode)
            ->first();
    }

    /**
     * Comprehensive QR code validation.
     * Returns array with validation result, booking (if found), and any errors.
     * Supports both qr_code_identifier (BK-XXXXXXXXXXXX) and booking_number (UUID) formats.
     */
    public function validateQrCode(string $qrCode): array
    {
        $errors = [];
        $booking = null;

        // Step 1: Try to find booking (supports both formats)
        $booking = $this->findBookingByQrCode($qrCode);
        if (!$booking) {
            $errors[] = 'Booking not found for this QR code';
            return [
                'is_valid' => false,
                'booking' => null,
                'errors' => $errors,
            ];
        }

        // Step 2: Basic booking validation
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
                'qr_code_format' => $this->isValidFormat($qrCode) ? 'BK-format' : 'booking_number',
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
