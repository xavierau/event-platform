<?php

namespace App\Services;

use App\DataTransferObjects\CheckInData;
use App\Enums\BookingStatusEnum;
use App\Enums\CheckInStatus;
use App\Models\Booking;
use App\Models\CheckInLog;
use App\Models\EventOccurrence;
use App\Models\User;
use App\Traits\CheckInLoggable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckInService
{
    use CheckInLoggable;
    public function __construct(
        private CheckInEligibilityService $eligibilityService
    ) {}

    /**
     * Process a check-in for a booking at a specific event occurrence.
     */
    public function processCheckIn(CheckInData $checkInData): array
    {
        $this->logMethodEntry('BOOKING_CHECKIN', __METHOD__, [
            'qr_code_identifier' => $checkInData->qr_code_identifier,
            'event_occurrence_id' => $checkInData->event_occurrence_id,
            'method' => $checkInData->method->value,
            'operator_user_id' => $checkInData->operator_user_id,
        ]);

        return DB::transaction(function () use ($checkInData) {
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Starting database transaction');

            // 1. Find the booking
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Looking up booking by QR code', [
                'qr_code_identifier' => $checkInData->qr_code_identifier,
            ]);

            $booking = Booking::with(['event', 'user', 'ticketDefinition'])
                ->byQrCode($checkInData->qr_code_identifier)
                ->first();

            // If not found by qr_code_identifier, try booking_number (for legacy QR codes)
            if (! $booking) {
                $this->logBusinessLogic('BOOKING_CHECKIN', 'Booking not found by QR code, trying booking number', [
                    'identifier' => $checkInData->qr_code_identifier,
                ]);

                $booking = Booking::with(['event', 'user', 'ticketDefinition'])
                    ->where('booking_number', $checkInData->qr_code_identifier)
                    ->first();
            }

            if (! $booking) {
                $this->logValidation('BOOKING_CHECKIN', 'Booking not found', [
                    'qr_code_identifier' => $checkInData->qr_code_identifier,
                ], false);

                return $this->createFailureResponse('Booking not found', CheckInStatus::FAILED_INVALID_CODE);
            }

            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Booking found', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'booking_status' => $booking->status->value,
                'event_id' => $booking->event_id,
                'user_id' => $booking->user_id,
                'quantity' => $booking->quantity,
                'successful_check_ins_count' => $booking->successful_check_ins_count,
            ]);

            // 2. Find the event occurrence
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Looking up event occurrence', [
                'event_occurrence_id' => $checkInData->event_occurrence_id,
            ]);

            $eventOccurrence = EventOccurrence::find($checkInData->event_occurrence_id);
            if (! $eventOccurrence) {
                $this->logValidation('BOOKING_CHECKIN', 'Event occurrence not found', [
                    'event_occurrence_id' => $checkInData->event_occurrence_id,
                ], false);

                return $this->createFailureResponse('Event occurrence not found', CheckInStatus::FAILED_INVALID_CODE);
            }

            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Event occurrence found', [
                'event_occurrence_id' => $eventOccurrence->id,
                'event_id' => $eventOccurrence->event_id,
                'name' => $eventOccurrence->name,
                'start_at' => $eventOccurrence->start_at,
                'end_at' => $eventOccurrence->end_at,
            ]);

            // 3. Find the operator
            $operator = $checkInData->operator_user_id ? User::find($checkInData->operator_user_id) : null;

            if ($checkInData->operator_user_id) {
                $this->logAuthorization('BOOKING_CHECKIN', 'Operator lookup', [
                    'operator_user_id' => $checkInData->operator_user_id,
                    'operator_found' => $operator !== null,
                    'operator_name' => $operator?->name,
                ], $operator !== null);
            }

            // 4. Validate eligibility
            $this->logBusinessLogic('BOOKING_CHECKIN', 'Validating check-in eligibility', [
                'booking_id' => $booking->id,
                'event_occurrence_id' => $eventOccurrence->id,
                'has_operator' => $operator !== null,
            ]);

            $eligibilityResult = $this->eligibilityService->validateEligibilityForOccurrence(
                $booking,
                $eventOccurrence,
                $operator
            );

            if (! $eligibilityResult['is_eligible']) {
                $status = $this->determineFailureStatus($eligibilityResult['errors']);

                $this->logBusinessLogic('BOOKING_CHECKIN', 'Eligibility check failed', [
                    'errors' => $eligibilityResult['errors'],
                    'failure_status' => $status->value,
                ]);

                return $this->createFailureResponse(
                    implode('; ', $eligibilityResult['errors']),
                    $status,
                    $booking,
                    $eventOccurrence,
                    $checkInData
                );
            }

            $this->logBusinessLogic('BOOKING_CHECKIN', 'Eligibility check passed', [
                'booking_id' => $booking->id,
            ]);

            // 5. Check if this will be the first successful check-in
            $isFirstCheckIn = $booking->successful_check_ins_count === 0;

            $this->logBusinessLogic('BOOKING_CHECKIN', 'First check-in status', [
                'is_first_check_in' => $isFirstCheckIn,
                'current_check_ins_count' => $booking->successful_check_ins_count,
            ]);

            // 6. Create successful check-in log
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Creating check-in log record', [
                'booking_id' => $booking->id,
                'event_occurrence_id' => $eventOccurrence->id,
                'status' => CheckInStatus::SUCCESSFUL->value,
            ]);

            $checkInLog = $this->createCheckInLog(
                $booking,
                $eventOccurrence,
                $checkInData,
                CheckInStatus::SUCCESSFUL
            );

            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Check-in log created', [
                'check_in_log_id' => $checkInLog->id,
            ]);

            // 7. Update booking status if this is the first successful check-in
            if ($isFirstCheckIn) {
                $this->logDatabaseOperation('BOOKING_CHECKIN', 'Updating booking status to USED', [
                    'booking_id' => $booking->id,
                    'old_status' => $booking->status->value,
                    'new_status' => BookingStatusEnum::USED->value,
                ]);

                $booking->update(['status' => BookingStatusEnum::USED]);

                $this->logDatabaseOperation('BOOKING_CHECKIN', 'Booking status updated', [
                    'booking_id' => $booking->id,
                ]);
            }

            // 8. Log success
            Log::info('Check-in successful', [
                'booking_id' => $booking->id,
                'event_occurrence_id' => $eventOccurrence->id,
                'operator_user_id' => $operator?->id,
                'check_in_log_id' => $checkInLog->id,
                'method' => $checkInData->method->value,
            ]);

            $freshBooking = $booking->fresh(['checkInLogs']);
            $remainingCheckIns = $this->eligibilityService->getRemainingCheckIns($freshBooking);

            $this->logMethodExit('BOOKING_CHECKIN', __METHOD__, [
                'success' => true,
                'check_in_log_id' => $checkInLog->id,
                'booking_id' => $booking->id,
                'remaining_check_ins' => $remainingCheckIns,
            ]);

            return [
                'success' => true,
                'message' => 'Check-in successful',
                'status' => CheckInStatus::SUCCESSFUL,
                'check_in_log' => $checkInLog,
                'booking' => $freshBooking,
                'remaining_check_ins' => $remainingCheckIns,
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
            $this->logDatabaseOperation('BOOKING_CHECKIN', 'Creating failed check-in log', [
                'booking_id' => $booking->id,
                'event_occurrence_id' => $eventOccurrence->id,
                'failure_status' => $status->value,
                'failure_message' => $message,
            ]);

            $this->createCheckInLog($booking, $eventOccurrence, $checkInData, $status);
        }

        $this->logBusinessLogic('BOOKING_CHECKIN', 'Check-in failed - creating failure response', [
            'message' => $message,
            'status' => $status->value,
            'booking_id' => $booking?->id,
            'booking_number' => $booking?->booking_number,
            'event_occurrence_id' => $eventOccurrence?->id,
            'qr_code_identifier' => $checkInData?->qr_code_identifier,
        ]);

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
