<?php

namespace App\Services;

use App\Contracts\MemberQrValidatorInterface;
use App\Models\User;
use App\ValueObjects\ValidationResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class MemberQrValidator implements MemberQrValidatorInterface
{
    /**
     * Required fields in membership QR data from MyProfile.vue
     */
    private const REQUIRED_FIELDS = [
        'userId',
        'userName', 
        'email',
        'membershipLevel',
    ];

    /**
     * Validate a membership QR code.
     */
    public function validate(string $qrCode): ValidationResult
    {
        try {
            // 1. Check JSON format
            if (!$this->isValidFormat($qrCode)) {
                return ValidationResult::failure('Invalid QR code format');
            }

            $membershipData = json_decode($qrCode, true);

            // 2. Check required fields
            if (!$this->hasRequiredFields($membershipData)) {
                return ValidationResult::failure('Missing required fields');
            }

            // 3. Check QR code expiration
            if (!$this->isNotExpired($membershipData)) {
                return ValidationResult::failure('QR code expired');
            }

            // 4. Find and validate user
            $user = User::find($membershipData['userId']);
            if (!$user) {
                return ValidationResult::failure('User not found');
            }

            // 5. Validate membership status
            if (!$this->hasValidMembership($user)) {
                return ValidationResult::failure('Invalid membership status');
            }

            // 6. Extract and store event context if present for check-in service
            if (isset($membershipData['eventContext'])) {
                $eventContext = $membershipData['eventContext'];
                $membershipData['_event_id'] = $eventContext['eventId'] ?? null;
                $membershipData['_event_occurrence_id'] = $eventContext['eventOccurrenceId'] ?? null;
            }

            return ValidationResult::success($user, $membershipData);

        } catch (Exception $e) {
            Log::error('MemberQrValidator validation error', [
                'error' => $e->getMessage(),
                'qr_preview' => substr($qrCode, 0, 100) . '...',
            ]);

            return ValidationResult::failure('Validation error occurred');
        }
    }

    /**
     * Check if QR code has valid JSON format.
     */
    public function isValidFormat(string $qrCode): bool
    {
        try {
            $decoded = json_decode($qrCode, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded);
        } catch (Exception) {
            return false;
        }
    }

    /**
     * Check if QR code timestamp is not expired.
     */
    public function isNotExpired(array $membershipData, int $expirationHours = 24): bool
    {
        // If no timestamp provided, consider it valid (for backward compatibility)
        if (!isset($membershipData['timestamp'])) {
            return true;
        }

        try {
            $timestamp = Carbon::parse($membershipData['timestamp']);
            $expiresAt = $timestamp->addHours($expirationHours);
            
            return now()->lte($expiresAt);
        } catch (Exception) {
            // If timestamp parsing fails, consider it expired
            return false;
        }
    }

    /**
     * Validate required fields in membership data.
     */
    public function hasRequiredFields(array $membershipData): bool
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($membershipData[$field]) || empty($membershipData[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if user has valid membership status.
     */
    public function hasValidMembership(User $user): bool
    {
        // For now, we allow all users to check in regardless of membership status
        // This can be enhanced later to check specific membership requirements
        
        // Basic validation: user must exist and be active
        if (!$user->exists) {
            return false;
        }

        // Optional: Check if user has active membership
        // Uncomment if stricter validation is needed:
        // return $user->hasMembership() && $user->currentMembership->isActive();

        return true;
    }
}