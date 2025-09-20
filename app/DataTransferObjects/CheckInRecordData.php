<?php

namespace App\DataTransferObjects;

use App\Enums\BookingStatusEnum;
use App\Enums\CheckInMethod;
use App\Enums\CheckInStatus;
use Carbon\Carbon;
use Spatie\LaravelData\Data;

class CheckInRecordData extends Data
{
    public function __construct(
        public int $id,
        public Carbon $check_in_timestamp,
        public CheckInStatus $status,
        public CheckInMethod $method,
        public ?string $location_description,
        public ?string $notes,
        public BookingData $booking,
        public EventData $event,
        public EventOccurrenceData $event_occurrence,
        public ?UserData $operator,
        public OrganizerData $organizer,
    ) {}

    public static function fromCheckInLog($checkInLog): self
    {
        // Ensure required relationships exist
        if (! $checkInLog->booking) {
            throw new \InvalidArgumentException('CheckInLog must have a booking relationship');
        }

        if (! $checkInLog->booking->event) {
            throw new \InvalidArgumentException('CheckInLog booking must have an event relationship');
        }

        if (! $checkInLog->booking->event->organizer) {
            throw new \InvalidArgumentException('CheckInLog booking event must have an organizer relationship');
        }

        if (! $checkInLog->booking->user) {
            throw new \InvalidArgumentException('CheckInLog booking must have a user relationship');
        }

        if (! $checkInLog->eventOccurrence) {
            throw new \InvalidArgumentException('CheckInLog must have an eventOccurrence relationship');
        }

        return new self(
            id: $checkInLog->id,
            check_in_timestamp: $checkInLog->check_in_timestamp,
            status: $checkInLog->status,
            method: $checkInLog->method,
            location_description: $checkInLog->location_description,
            notes: $checkInLog->notes,
            booking: BookingData::from([
                'id' => $checkInLog->booking->id,
                'booking_number' => $checkInLog->booking->booking_number,
                'status' => $checkInLog->booking->status,
                'quantity' => $checkInLog->booking->quantity,
                'user' => UserData::from([
                    'id' => $checkInLog->booking->user->id,
                    'name' => $checkInLog->booking->user->name,
                    'email' => $checkInLog->booking->user->email,
                ]),
            ]),
            event: EventData::from([
                'id' => $checkInLog->booking->event->id,
                'name' => $checkInLog->booking->event->name,
                'organizer_id' => $checkInLog->booking->event->organizer_id,
            ]),
            event_occurrence: EventOccurrenceData::from([
                'id' => $checkInLog->eventOccurrence->id,
                'name' => $checkInLog->eventOccurrence->name,
                'start_at' => $checkInLog->eventOccurrence->start_at,
                'end_at' => $checkInLog->eventOccurrence->end_at,
                'venue_name' => $checkInLog->eventOccurrence->venue_name,
            ]),
            operator: $checkInLog->operator ? UserData::from([
                'id' => $checkInLog->operator->id,
                'name' => $checkInLog->operator->name,
                'email' => $checkInLog->operator->email,
            ]) : null,
            organizer: OrganizerData::from([
                'id' => $checkInLog->booking->event->organizer->id,
                'name' => $checkInLog->booking->event->organizer->name,
                'slug' => $checkInLog->booking->event->organizer->slug,
            ]),
        );
    }
}

class BookingData extends Data
{
    public function __construct(
        public int $id,
        public string $booking_number,
        public BookingStatusEnum $status,
        public int $quantity,
        public UserData $user,
    ) {}
}

class EventData extends Data
{
    public function __construct(
        public int $id,
        public array|string $name,
        public int $organizer_id,
    ) {}
}

class EventOccurrenceData extends Data
{
    public function __construct(
        public int $id,
        public array|string $name,
        public Carbon $start_at,
        public Carbon $end_at,
        public ?string $venue_name,
    ) {}
}

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}

class OrganizerData extends Data
{
    public function __construct(
        public int $id,
        public array|string $name,
        public string $slug,
    ) {}
}
