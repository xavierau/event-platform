<?php

namespace App\Services;

use App\Enums\RoleNameEnum;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Organizer;

class CheckInEligibilityService
{


    /**
     * Valid booking statuses for check-in
     */
    public const VALID_BOOKING_STATUSES = ['confirmed', 'used'];

    /**
     * Validate if a booking is eligible for check-in.
     *
     * @param Booking $booking
     * @param User|null $operator The user attempting to perform the check-in
     * @return array
     */
    public function validateEligibility(Booking $booking, $operator = null): array
    {
        $errors = [];
        $timingInfo = $this->getTimingInfo($booking);

        // Load relationships if not already loaded
        $booking->load(['event', 'checkInLogs']);

        // 1. Check operator authorization
        if ($operator) {
            $operatorError = $this->validateOperatorAuthorization($operator, $booking);
            if ($operatorError) {
                $errors[] = $operatorError;
            }
        }

        // 2. Check booking status
        if (!in_array($booking->status->value, self::VALID_BOOKING_STATUSES)) {
            $errors[] = "Booking status is not valid for check-in (current status: {$booking->status->value})";
        }

        // 3. Check event timing (removed window constraints - operators can check in anytime)

        // 4. Check max allowed check-ins
        $checkInCountError = $this->validateCheckInCount($booking);
        if ($checkInCountError) {
            $errors[] = $checkInCountError;
        }

        $isEligible = empty($errors);

        // Log the validation result
        $this->logValidationResult($booking, $isEligible, $errors);

        return [
            'is_eligible' => $isEligible,
            'booking' => $booking,
            'errors' => $errors,
            'timing_info' => $timingInfo,
            'check_in_count' => $booking->successful_check_ins_count,
            'max_allowed_check_ins' => $booking->max_allowed_check_ins,
        ];
    }

    /**
     * Validate operator authorization for check-in.
     */
    private function validateOperatorAuthorization(User $operator, Booking $booking): ?string
    {
        // Check if operator is authorized to check in for this event
        if ($operator->hasRole(RoleNameEnum::ADMIN)) {
            // Admin can check in for any event
            return null;
        }

        // Check if user has organizer entity membership for this event's organizer
        $userOrganizerIds = Organizer::whereHas('users', function ($query) use ($operator) {
            $query->where('user_id', $operator->id);
        })->pluck('organizers.id');

        if (!$userOrganizerIds->contains($booking->event->organizer_id)) {
            return 'You are not authorized to check in for this event.';
        }

        return null; // Authorized
    }



    /**
     * Validate check-in count against maximum allowed.
     */
    private function validateCheckInCount(Booking $booking): ?string
    {
        $successfulCheckIns = $booking->successful_check_ins_count;
        $maxAllowed = $booking->max_allowed_check_ins;

        if ($successfulCheckIns >= $maxAllowed) {
            return "Maximum allowed check-ins reached ({$successfulCheckIns}/{$maxAllowed})";
        }

        return null;
    }

    /**
     * Validate that the ticket is valid for the specific event occurrence.
     * This checks the many-to-many relationship between tickets and occurrences.
     */
    private function validateTicketOccurrenceRelationship(Booking $booking, $eventOccurrence): ?string
    {
        // If no ticket definition, we can't validate the relationship
        if (!$booking->ticketDefinition) {
            return 'Ticket definition not found for this booking';
        }

        // Get the occurrence IDs that this ticket is valid for
        $validOccurrenceIds = $booking->ticketDefinition->eventOccurrences->pluck('id')->toArray();

        // Check if the target occurrence is in the list of valid occurrences for this ticket
        if (!in_array($eventOccurrence->id, $validOccurrenceIds)) {
            return 'This ticket is not valid for the selected event occurrence';
        }

        return null; // Valid
    }

    /**
     * Get timing information for the booking.
     * Note: Since bookings are now associated with events (not specific occurrences),
     * this returns general event information.
     */
    private function getTimingInfo(Booking $booking): array
    {
        if (!$booking->event) {
            return [];
        }

        return [
            'event_id' => $booking->event->id,
            'event_name' => $booking->event->name,
            'current_time' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Log the validation result for audit purposes.
     */
    private function logValidationResult(Booking $booking, bool $isEligible, array $errors): void
    {
        $logData = [
            'booking_id' => $booking->id,
            'booking_status' => $booking->status->value,
            'is_eligible' => $isEligible,
            'successful_check_ins' => $booking->successful_check_ins_count,
            'max_allowed_check_ins' => $booking->max_allowed_check_ins,
        ];

        if ($isEligible) {
            Log::info('Check-in eligibility validation passed', $logData);
        } else {
            Log::warning('Check-in eligibility validation failed', array_merge($logData, [
                'errors' => $errors,
            ]));
        }
    }

    /**
     * Check if check-in is currently available for an event occurrence.
     * Note: With window constraints removed, operators can check in anytime.
     */
    public function isCheckInWindowOpen($eventOccurrence): bool
    {
        // With window constraints removed, check-in is always available if event occurrence exists
        return $eventOccurrence !== null;
    }

    /**
     * Validate that a booking is eligible for check-in at a specific event occurrence.
     * This is the new method that takes both booking and event occurrence.
     */
    public function validateEligibilityForOccurrence(Booking $booking, $eventOccurrence, $operator = null): array
    {
        $errors = [];

        // Load relationships if not already loaded
        $booking->load(['event', 'checkInLogs', 'ticketDefinition.eventOccurrences']);

        // 1. Check that the event occurrence belongs to the same event as the booking
        if ($eventOccurrence && $booking->event_id !== $eventOccurrence->event_id) {
            $errors[] = 'The event occurrence does not belong to the same event as this booking';
        }

        // 2. Check that the ticket is valid for this specific occurrence
        if ($eventOccurrence && $booking->ticketDefinition) {
            $ticketOccurrenceError = $this->validateTicketOccurrenceRelationship($booking, $eventOccurrence);
            if ($ticketOccurrenceError) {
                $errors[] = $ticketOccurrenceError;
            }
        }

        // 3. Check operator authorization
        if ($operator) {
            $operatorError = $this->validateOperatorAuthorization($operator, $booking);
            if ($operatorError) {
                $errors[] = $operatorError;
            }
        }

        // 4. Check booking status
        if (!in_array($booking->status->value, self::VALID_BOOKING_STATUSES)) {
            $errors[] = "Booking status is not valid for check-in (current status: {$booking->status->value})";
        }

        // 5. Check max allowed check-ins
        $checkInCountError = $this->validateCheckInCount($booking);
        if ($checkInCountError) {
            $errors[] = $checkInCountError;
        }

        $isEligible = empty($errors);

        // Log the validation result
        $this->logValidationResultForOccurrence($booking, $eventOccurrence, $isEligible, $errors);

        return [
            'is_eligible' => $isEligible,
            'booking' => $booking,
            'event_occurrence' => $eventOccurrence,
            'errors' => $errors,
            'timing_info' => $this->getTimingInfoForOccurrence($booking, $eventOccurrence),
            'check_in_count' => $booking->successful_check_ins_count,
            'max_allowed_check_ins' => $booking->max_allowed_check_ins,
        ];
    }

    /**
     * Get timing information for a specific occurrence.
     */
    private function getTimingInfoForOccurrence(Booking $booking, $eventOccurrence): array
    {
        if (!$eventOccurrence) {
            return $this->getTimingInfo($booking);
        }

        $eventStart = Carbon::parse($eventOccurrence->start_at);
        $eventEnd = Carbon::parse($eventOccurrence->end_at);

        return [
            'event_id' => $booking->event->id,
            'event_name' => $booking->event->name ?? 'Unknown Event',
            'occurrence_id' => $eventOccurrence->id,
            'occurrence_starts_at' => $eventStart->toISOString(),
            'occurrence_ends_at' => $eventEnd->toISOString(),
            'current_time' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Log the validation result for a specific occurrence.
     */
    private function logValidationResultForOccurrence(Booking $booking, $eventOccurrence, bool $isEligible, array $errors): void
    {
        $logData = [
            'booking_id' => $booking->id,
            'event_id' => $booking->event_id,
            'event_occurrence_id' => $eventOccurrence?->id,
            'booking_status' => $booking->status->value,
            'is_eligible' => $isEligible,
            'successful_check_ins' => $booking->successful_check_ins_count,
            'max_allowed_check_ins' => $booking->max_allowed_check_ins,
        ];

        if ($isEligible) {
            Log::info('Check-in eligibility validation passed for occurrence', $logData);
        } else {
            Log::warning('Check-in eligibility validation failed for occurrence', array_merge($logData, [
                'errors' => $errors,
            ]));
        }
    }

    /**
     * Get the remaining check-ins available for a booking.
     */
    public function getRemainingCheckIns(Booking $booking): int
    {
        return max(0, $booking->max_allowed_check_ins - $booking->successful_check_ins_count);
    }

    /**
     * Check if a booking can be checked in (combines all validations).
     */
    public function canCheckIn(Booking $booking, $operator = null): bool
    {
        $result = $this->validateEligibility($booking, $operator);
        return $result['is_eligible'];
    }
}
