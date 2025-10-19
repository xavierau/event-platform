<?php

namespace App\Services;

use App\Contracts\MemberCheckInServiceInterface;
use App\Contracts\MemberQrValidatorInterface;
use App\DataTransferObjects\MemberCheckInData;
use App\Models\MemberCheckIn;
use App\Models\User;
use App\Traits\CheckInLoggable;
use App\ValueObjects\CheckInResult;
use Exception;
use Illuminate\Support\Facades\Log;

class MemberCheckInService implements MemberCheckInServiceInterface
{
    use CheckInLoggable;
    public function __construct(
        private readonly MemberQrValidatorInterface $qrValidator
    ) {}

    /**
     * Process a member check-in from QR code.
     */
    public function processCheckIn(string $qrCode, array $context): CheckInResult
    {
        $this->logMethodEntry('MEMBER_CHECKIN', __METHOD__, [
            'qr_code_length' => strlen($qrCode),
            'context' => $this->sanitizeContext($context),
        ]);

        try {
            // 1. Validate required context
            $this->logValidation('MEMBER_CHECKIN', 'Validating required context', [
                'has_scanner_id' => isset($context['scanner_id']),
                'has_location' => isset($context['location']),
                'has_device_identifier' => isset($context['device_identifier']),
            ]);

            if (!isset($context['scanner_id'])) {
                $this->logValidation('MEMBER_CHECKIN', 'Context validation failed: Missing scanner_id', [], false);
                return CheckInResult::failure('Scanner user ID is required');
            }

            // 2. Validate QR code
            $this->logValidation('MEMBER_CHECKIN', 'Starting QR code validation', [
                'validator' => get_class($this->qrValidator),
            ]);

            $validation = $this->qrValidator->validate($qrCode);

            if (!$validation->isValid()) {
                $this->logValidation('MEMBER_CHECKIN', 'QR validation failed', [
                    'error' => $validation->getError(),
                ], false);

                return CheckInResult::failure($validation->getError());
            }

            $member = $validation->getUser();
            $this->logDatabaseOperation('MEMBER_CHECKIN', 'Member lookup successful', [
                'member_id' => $member->id,
                'member_email' => $member->email,
                'member_name' => $member->name,
            ]);

            // 3. Extract event context from QR data
            $membershipData = $validation->getData();
            $eventId = $membershipData['_event_id'] ?? null;
            $eventOccurrenceId = $membershipData['_event_occurrence_id'] ?? null;

            $this->logBusinessLogic('MEMBER_CHECKIN', 'Extracted event context from QR data', [
                'event_id' => $eventId,
                'event_occurrence_id' => $eventOccurrenceId,
                'membership_type' => $membershipData['membership_type'] ?? null,
                'membership_status' => $membershipData['status'] ?? null,
                'valid_from' => $membershipData['valid_from'] ?? null,
                'valid_until' => $membershipData['valid_until'] ?? null,
            ]);

            // 4. Create check-in data
            $checkInData = MemberCheckInData::from([
                'user_id' => $member->id,
                'scanned_by_user_id' => $context['scanner_id'],
                'scanned_at' => now()->format('Y-m-d H:i:s'),
                'location' => $context['location'] ?? null,
                'notes' => $context['notes'] ?? null,
                'device_identifier' => $context['device_identifier'] ?? null,
                'membership_data' => $membershipData,
                'event_id' => $eventId,
                'event_occurrence_id' => $eventOccurrenceId,
            ]);

            $this->logBusinessLogic('MEMBER_CHECKIN', 'Check-in data DTO created', [
                'user_id' => $checkInData->user_id,
                'scanned_by_user_id' => $checkInData->scanned_by_user_id,
                'location' => $checkInData->location,
                'event_id' => $checkInData->event_id,
            ]);

            // 5. Log the check-in to database
            $this->logDatabaseOperation('MEMBER_CHECKIN', 'Creating check-in record', [
                'table' => 'member_check_ins',
            ]);

            $checkIn = $this->logCheckIn($checkInData);

            $this->logDatabaseOperation('MEMBER_CHECKIN', 'Check-in record created successfully', [
                'check_in_id' => $checkIn->id,
                'member_id' => $checkIn->user_id,
            ]);

            Log::info('Member check-in successful', [
                'member_id' => $member->id,
                'scanner_id' => $context['scanner_id'],
                'location' => $context['location'] ?? null,
                'check_in_id' => $checkIn->id,
            ]);

            $this->logMethodExit('MEMBER_CHECKIN', __METHOD__, [
                'success' => true,
                'check_in_id' => $checkIn->id,
                'member_id' => $member->id,
            ]);

            return CheckInResult::success($checkIn, 'Member check-in successful');

        } catch (Exception $e) {
            $this->logCheckInError('MEMBER_CHECKIN', 'Check-in processing exception', $e, [
                'context' => $this->sanitizeContext($context),
                'qr_code_length' => strlen($qrCode),
            ]);

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
        $this->logMethodEntry('MEMBER_CHECKIN', __METHOD__, [
            'qr_code_length' => strlen($qrCode),
        ]);

        $this->logValidation('MEMBER_CHECKIN', 'Validating member QR code', [
            'validator' => get_class($this->qrValidator),
        ]);

        $validation = $this->qrValidator->validate($qrCode);

        if (!$validation->isValid()) {
            $this->logValidation('MEMBER_CHECKIN', 'QR validation failed', [
                'error' => $validation->getError(),
            ], false);

            return CheckInResult::failure($validation->getError());
        }

        $member = $validation->getUser();
        $membershipData = $validation->getData();

        $this->logValidation('MEMBER_CHECKIN', 'QR validation successful', [
            'member_id' => $member->id,
            'member_email' => $member->email,
            'membership_type' => $membershipData['membership_type'] ?? null,
            'membership_status' => $membershipData['status'] ?? null,
        ], true);

        $this->logMethodExit('MEMBER_CHECKIN', __METHOD__, [
            'success' => true,
            'member_id' => $member->id,
        ]);

        return CheckInResult::validationSuccess(
            $member,
            $membershipData,
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