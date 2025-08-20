<?php

namespace App\Services;

use App\DataTransferObjects\CheckInData;
use App\Enums\BookingStatusEnum;
use App\Enums\CheckInStatus;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\EventOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckInService
{
    public function __construct(
        private CheckInEligibilityService $eligibilityService
    ) {}

    /**
     * Process a check-in for a booking at a specific event occurrence.
     */
    public function processCheckIn(CheckInData $checkInData): array
    {
        return DB::transaction(function () use ($checkInData) {
            // 1. Find the booking
            $booking = Booking::with(['event', 'user', 'ticketDefinition'])
                ->byQrCode($checkInData->qr_code_identifier)
                ->first();

            // If not found by qr_code_identifier, try booking_number (for legacy QR codes)
            if (! $booking) {
                $booking = Booking::with(['event', 'user', 'ticketDefinition'])
                    ->where('booking_number', $checkInData->qr_code_identifier)
                    ->first();
            }

            if (! $booking) {
                return $this->createFailureResponse('Booking not found', CheckInStatus::FAILED_INVALID_CODE);
            }

            // 2. Find the event occurrence
            $eventOccurrence = EventOccurrence::find($checkInData->event_occurrence_id);
            if (! $eventOccurrence) {
                return $this->createFailureResponse('Event occurrence not found', CheckInStatus::FAILED_INVALID_CODE);
            }

            // 3. Find the operator
            $operator = $checkInData->operator_user_id ? User::find($checkInData->operator_user_id) : null;

            // 4. Validate eligibility
            $eligibilityResult = $this->eligibilityService->validateEligibilityForOccurrence(
                $booking,
                $eventOccurrence,
                $operator
            );

            if (! $eligibilityResult['is_eligible']) {
                $status = $this->determineFailureStatus($eligibilityResult['errors']);

                return $this->createFailureResponse(
                    implode('; ', $eligibilityResult['errors']),
                    $status,
                    $booking,
                    $eventOccurrence,
                    $checkInData
                );
            }

            // 5. Check if this will be the first successful check-in
            $isFirstCheckIn = $booking->successful_check_ins_count === 0;

            // 6. Create successful check-in log
            $checkInLog = $this->createCheckInLog(
                $booking,
                $eventOccurrence,
                $checkInData,
                CheckInStatus::SUCCESSFUL
            );

            // 7. Update booking status if this is the first successful check-in
            if ($isFirstCheckIn) {
                $booking->update(['status' => BookingStatusEnum::USED]);
            }

            // 8. Log success
            Log::info('Check-in successful', [
                'booking_id' => $booking->id,
                'event_occurrence_id' => $eventOccurrence->id,
                'operator_user_id' => $operator?->id,
                'check_in_log_id' => $checkInLog->id,
                'method' => $checkInData->method->value,
            ]);

            return [
                'success' => true,
                'message' => 'Check-in successful',
                'check_in_log' => $checkInLog,
                'booking' => $booking->fresh(['checkInLogs']),
                'remaining_check_ins' => $this->eligibilityService->getRemainingCheckIns($booking->fresh()),
            ];
        });
    }

    /**
     * Get detailed check-in history for a booking.
     *
     * @param  User|null  $user  Optional user to filter check-ins by organizer access
     */
    public function getCheckInHistory(Booking $booking, ?User $user = null): array
    {
        $query = $booking->checkInLogs()
            ->with(['eventOccurrence.event', 'operator']);

        // Filter by user's accessible organizers if provided and not platform admin
        if ($user && ! $user->hasRole(RoleNameEnum::ADMIN)) {
            $userOrganizerIds = $user->activeOrganizers()->pluck('organizers.id');

            if ($userOrganizerIds->isNotEmpty()) {
                $query->whereHas('eventOccurrence.event', function ($q) use ($userOrganizerIds) {
                    $q->whereIn('organizer_id', $userOrganizerIds);
                });
            } else {
                // User has no organizer access - return empty history
                $query->whereRaw('1 = 0'); // Force empty result
            }
        }

        $checkInLogs = $query->orderBy('check_in_timestamp', 'desc')->get();

        return [
            'total_check_ins' => $checkInLogs->count(),
            'successful_check_ins' => $checkInLogs->where('status', CheckInStatus::SUCCESSFUL)->count(),
            'failed_check_ins' => $checkInLogs->where('status', '!=', CheckInStatus::SUCCESSFUL)->count(),
            'remaining_check_ins' => $this->eligibilityService->getRemainingCheckIns($booking),
            'max_allowed_check_ins' => $booking->max_allowed_check_ins,
            'check_in_logs' => $checkInLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'timestamp' => $log->check_in_timestamp,
                    'status' => $log->status->value,
                    'method' => $log->method->value,
                    'event_occurrence' => $log->eventOccurrence ? [
                        'id' => $log->eventOccurrence->id,
                        'name' => $log->eventOccurrence->name,
                        'start_at' => $log->eventOccurrence->start_at,
                        'end_at' => $log->eventOccurrence->end_at,
                    ] : null,
                    'operator' => $log->operator ? [
                        'id' => $log->operator->id,
                        'name' => $log->operator->name,
                    ] : null,
                    'location_description' => $log->location_description,
                    'notes' => $log->notes,
                ];
            }),
        ];
    }

    /**
     * Create a check-in log entry.
     */
    private function createCheckInLog(
        Booking $booking,
        EventOccurrence $eventOccurrence,
        CheckInData $checkInData,
        CheckInStatus $status
    ): CheckInLog {
        return CheckInLog::create([
            'booking_id' => $booking->id,
            'event_occurrence_id' => $eventOccurrence->id,
            'check_in_timestamp' => Carbon::now(),
            'method' => $checkInData->method,
            'device_identifier' => $checkInData->device_identifier,
            'location_description' => $checkInData->location_description,
            'operator_user_id' => $checkInData->operator_user_id,
            'status' => $status,
            'notes' => $checkInData->notes,
        ]);
    }

    /**
     * Create a failure response and log the failed attempt.
     */
    private function createFailureResponse(
        string $message,
        CheckInStatus $status,
        ?Booking $booking = null,
        ?EventOccurrence $eventOccurrence = null,
        ?CheckInData $checkInData = null
    ): array {
        // Log the failed attempt if we have enough information
        if ($booking && $eventOccurrence && $checkInData) {
            $this->createCheckInLog($booking, $eventOccurrence, $checkInData, $status);
        }

        Log::warning('Check-in failed', [
            'message' => $message,
            'status' => $status->value,
            'booking_id' => $booking?->id,
            'event_occurrence_id' => $eventOccurrence?->id,
            'qr_code' => $checkInData?->qr_code_identifier,
        ]);

        return [
            'success' => false,
            'message' => $message,
            'status' => $status,
            'booking' => $booking,
        ];
    }

    /**
     * Determine the appropriate failure status based on validation errors.
     */
    private function determineFailureStatus(array $errors): CheckInStatus
    {
        $errorString = implode(' ', $errors);

        if (str_contains($errorString, 'Maximum allowed check-ins reached')) {
            return CheckInStatus::FAILED_MAX_USES_REACHED;
        }

        if (str_contains($errorString, 'not confirmed')) {
            return CheckInStatus::FAILED_INVALID_CODE;
        }

        if (str_contains($errorString, 'not valid for the selected event occurrence')) {
            return CheckInStatus::FAILED_INVALID_CODE;
        }

        if (str_contains($errorString, 'operator')) {
            return CheckInStatus::FAILED_INVALID_CODE;
        }

        // Default failure status
        return CheckInStatus::FAILED_INVALID_CODE;
    }

    /**
     * Check if a booking can be checked in at a specific occurrence.
     */
    public function validateCheckInEligibility(string $qrCode, int $eventOccurrenceId, ?User $operator = null): array
    {
        $booking = Booking::with(['event', 'user', 'ticketDefinition', 'checkInLogs'])
            ->byQrCode($qrCode)
            ->first();

        if (! $booking) {
            return [
                'is_eligible' => false,
                'errors' => ['Booking not found'],
                'booking' => null,
                'event_occurrence' => null,
            ];
        }

        $eventOccurrence = EventOccurrence::find($eventOccurrenceId);
        if (! $eventOccurrence) {
            return [
                'is_eligible' => false,
                'errors' => ['Event occurrence not found'],
                'booking' => $booking,
                'event_occurrence' => null,
            ];
        }

        return $this->eligibilityService->validateEligibilityForOccurrence(
            $booking,
            $eventOccurrence,
            $operator
        );
    }
}
