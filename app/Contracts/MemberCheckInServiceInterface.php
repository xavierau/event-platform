<?php

namespace App\Contracts;

use App\DataTransferObjects\MemberCheckInData;
use App\Models\MemberCheckIn;
use App\Models\User;
use App\ValueObjects\CheckInResult;

interface MemberCheckInServiceInterface
{
    /**
     * Process a member check-in from QR code.
     *
     * @param string $qrCode JSON QR code from MyProfile.vue
     * @param array $context Additional context (scanner_id, location, etc.)
     * @return CheckInResult
     */
    public function processCheckIn(string $qrCode, array $context): CheckInResult;

    /**
     * Log a member check-in event.
     *
     * @param MemberCheckInData $data
     * @return MemberCheckIn
     */
    public function logCheckIn(MemberCheckInData $data): MemberCheckIn;

    /**
     * Get check-in history for a specific member.
     *
     * @param User $member
     * @param int $limit
     * @return array
     */
    public function getCheckInHistory(User $member, int $limit = 50): array;

    /**
     * Get recent check-ins performed by a scanner.
     *
     * @param User $scanner
     * @param int $hours
     * @return array
     */
    public function getRecentCheckInsByScanner(User $scanner, int $hours = 24): array;

    /**
     * Validate QR code and return member information.
     *
     * @param string $qrCode
     * @return CheckInResult
     */
    public function validateMemberQr(string $qrCode): CheckInResult;
}