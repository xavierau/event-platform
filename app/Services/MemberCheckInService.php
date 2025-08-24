<?php

namespace App\Services;

use App\Contracts\MemberCheckInServiceInterface;
use App\Contracts\MemberQrValidatorInterface;
use App\DataTransferObjects\MemberCheckInData;
use App\Models\MemberCheckIn;
use App\Models\User;
use App\ValueObjects\CheckInResult;
use Exception;
use Illuminate\Support\Facades\Log;

class MemberCheckInService implements MemberCheckInServiceInterface
{
    public function __construct(
        private readonly MemberQrValidatorInterface $qrValidator
    ) {}

    /**
     * Process a member check-in from QR code.
     */
    public function processCheckIn(string $qrCode, array $context): CheckInResult
    {
        try {
            // 1. Validate required context
            if (!isset($context['scanner_id'])) {
                return CheckInResult::failure('Scanner user ID is required');
            }

            // 2. Validate QR code
            $validation = $this->qrValidator->validate($qrCode);
            if (!$validation->isValid()) {
                return CheckInResult::failure($validation->getError());
            }

            // 3. Extract event context from QR data
            $membershipData = $validation->getData();
            $eventId = $membershipData['_event_id'] ?? null;
            $eventOccurrenceId = $membershipData['_event_occurrence_id'] ?? null;

            // 4. Create check-in data
            $checkInData = MemberCheckInData::from([
                'user_id' => $validation->getUser()->id,
                'scanned_by_user_id' => $context['scanner_id'],
                'scanned_at' => now()->format('Y-m-d H:i:s'),
                'location' => $context['location'] ?? null,
                'notes' => $context['notes'] ?? null,
                'device_identifier' => $context['device_identifier'] ?? null,
                'membership_data' => $membershipData,
                'event_id' => $eventId,
                'event_occurrence_id' => $eventOccurrenceId,
            ]);

            // 5. Log the check-in
            $checkIn = $this->logCheckIn($checkInData);

            Log::info('Member check-in successful', [
                'member_id' => $validation->getUser()->id,
                'scanner_id' => $context['scanner_id'],
                'location' => $context['location'] ?? null,
                'check_in_id' => $checkIn->id,
            ]);

            return CheckInResult::success($checkIn, 'Member check-in successful');

        } catch (Exception $e) {
            Log::error('Member check-in processing failed', [
                'error' => $e->getMessage(),
                'context' => $context,
                'trace' => $e->getTraceAsString(),
            ]);

            return CheckInResult::failure('Check-in processing failed');
        }
    }

    /**
     * Validate QR code and return member information.
     */
    public function validateMemberQr(string $qrCode): CheckInResult
    {
        $validation = $this->qrValidator->validate($qrCode);

        if (!$validation->isValid()) {
            return CheckInResult::failure($validation->getError());
        }

        return CheckInResult::validationSuccess(
            $validation->getUser(),
            $validation->getData(),
            'Member QR validation successful'
        );
    }

    /**
     * Log a member check-in event.
     */
    public function logCheckIn(MemberCheckInData $data): MemberCheckIn
    {
        return MemberCheckIn::create([
            'user_id' => $data->user_id,
            'scanned_by_user_id' => $data->scanned_by_user_id,
            'scanned_at' => $data->scanned_at,
            'location' => $data->location,
            'notes' => $data->notes,
            'device_identifier' => $data->device_identifier,
            'membership_data' => $data->membership_data,
            'event_id' => $data->event_id,
            'event_occurrence_id' => $data->event_occurrence_id,
        ]);
    }

    /**
     * Get check-in history for a specific member.
     */
    public function getCheckInHistory(User $member, int $limit = 50): array
    {
        $checkIns = MemberCheckIn::with(['scanner:id,name'])
            ->forMember($member)
            ->orderBy('scanned_at', 'desc')
            ->limit($limit)
            ->get();

        return $checkIns->map(function (MemberCheckIn $checkIn) {
            return [
                'id' => $checkIn->id,
                'scanned_at' => $checkIn->scanned_at,
                'location' => $checkIn->location,
                'notes' => $checkIn->notes,
                'device_identifier' => $checkIn->device_identifier,
                'scanner_name' => $checkIn->scanner->name,
                'membership_data' => $checkIn->membership_data,
            ];
        })->toArray();
    }

    /**
     * Get recent check-ins performed by a scanner.
     */
    public function getRecentCheckInsByScanner(User $scanner, int $hours = 24): array
    {
        $checkIns = MemberCheckIn::with(['member:id,name,email'])
            ->byScanner($scanner)
            ->recent($hours)
            ->orderBy('scanned_at', 'desc')
            ->get();

        return $checkIns->map(function (MemberCheckIn $checkIn) {
            return [
                'id' => $checkIn->id,
                'member_name' => $checkIn->member->name,
                'member_email' => $checkIn->member->email,
                'scanned_at' => $checkIn->scanned_at,
                'location' => $checkIn->location,
                'notes' => $checkIn->notes,
                'membership_data' => $checkIn->membership_data,
            ];
        })->toArray();
    }
}