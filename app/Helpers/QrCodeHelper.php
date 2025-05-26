<?php

namespace App\Helpers;

use App\Models\Booking;
use Illuminate\Support\Str;

class QrCodeHelper
{
    /**
     * QR code format constants
     */
    public const PREFIX = 'BK-';
    public const SUFFIX_LENGTH = 12;
    public const TOTAL_LENGTH = 15; // BK- (3) + 12 characters
    public const PATTERN = '/^BK-[A-Z0-9]{12}$/';

    /**
     * Generate a unique QR code identifier for bookings.
     * Format: BK-XXXXXXXXXXXX (BK- prefix + 12 alphanumeric characters)
     */
    public static function generate(): string
    {
        do {
            $identifier = self::PREFIX . strtoupper(Str::random(self::SUFFIX_LENGTH));
        } while (Booking::where('qr_code_identifier', $identifier)->exists());

        return $identifier;
    }

    /**
     * Validate QR code format.
     * Expected format: BK-XXXXXXXXXXXX (BK- prefix followed by exactly 12 alphanumeric characters)
     */
    public static function isValidFormat(string $qrCode): bool
    {
        return preg_match(self::PATTERN, $qrCode) === 1;
    }

    /**
     * Extract the suffix (12-character part) from a QR code.
     * Returns null if the format is invalid.
     */
    public static function extractSuffix(string $qrCode): ?string
    {
        if (!self::isValidFormat($qrCode)) {
            return null;
        }

        return substr($qrCode, strlen(self::PREFIX));
    }

    /**
     * Create a QR code from a suffix.
     * The suffix must be exactly 12 alphanumeric characters.
     */
    public static function fromSuffix(string $suffix): ?string
    {
        if (strlen($suffix) !== self::SUFFIX_LENGTH || !ctype_alnum($suffix)) {
            return null;
        }

        $qrCode = self::PREFIX . strtoupper($suffix);

        return self::isValidFormat($qrCode) ? $qrCode : null;
    }

    /**
     * Get the QR code pattern for validation.
     */
    public static function getPattern(): string
    {
        return self::PATTERN;
    }

    /**
     * Get the expected QR code format description.
     */
    public static function getFormatDescription(): string
    {
        return sprintf(
            'QR code must be in format: %sXXXXXXXXXXXX (prefix "%s" followed by exactly %d alphanumeric characters)',
            self::PREFIX,
            self::PREFIX,
            self::SUFFIX_LENGTH
        );
    }

    /**
     * Generate multiple unique QR codes at once.
     * Useful for bulk operations.
     */
    public static function generateBatch(int $count): array
    {
        $qrCodes = [];
        $existingCodes = Booking::whereNotNull('qr_code_identifier')
            ->pluck('qr_code_identifier')
            ->toArray();

        while (count($qrCodes) < $count) {
            $candidate = self::PREFIX . strtoupper(Str::random(self::SUFFIX_LENGTH));

            // Check against both existing DB codes and already generated codes in this batch
            if (!in_array($candidate, $existingCodes) && !in_array($candidate, $qrCodes)) {
                $qrCodes[] = $candidate;
            }
        }

        return $qrCodes;
    }
}
