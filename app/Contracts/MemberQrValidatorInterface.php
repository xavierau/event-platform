<?php

namespace App\Contracts;

use App\Models\User;
use App\ValueObjects\ValidationResult;

interface MemberQrValidatorInterface
{
    /**
     * Validate a membership QR code.
     *
     * @param string $qrCode The JSON QR code from MyProfile.vue
     * @return ValidationResult
     */
    public function validate(string $qrCode): ValidationResult;

    /**
     * Check if QR code has valid JSON format.
     *
     * @param string $qrCode
     * @return bool
     */
    public function isValidFormat(string $qrCode): bool;

    /**
     * Check if QR code timestamp is not expired.
     *
     * @param array $membershipData
     * @param int $expirationHours
     * @return bool
     */
    public function isNotExpired(array $membershipData, int $expirationHours = 24): bool;

    /**
     * Validate required fields in membership data.
     *
     * @param array $membershipData
     * @return bool
     */
    public function hasRequiredFields(array $membershipData): bool;

    /**
     * Check if user has valid membership status.
     *
     * @param User $user
     * @return bool
     */
    public function hasValidMembership(User $user): bool;
}