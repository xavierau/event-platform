<?php

namespace App\DataTransferObjects;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class MemberCheckInRecordData extends Data
{
    public function __construct(
        public int $id,
        public Carbon $scanned_at,
        public ?string $location,
        public ?string $notes,
        public ?string $device_identifier,
        public ?array $membership_data,
        public ?int $event_id,
        public ?int $event_occurrence_id,
        public UserData $member,
        public ?UserData $scanner,
        public ?EventData $event,
        public ?EventOccurrenceData $event_occurrence,
    ) {}

    public static function fromMemberCheckIn($memberCheckIn): self
    {
        // Ensure required relationships exist
        if (! $memberCheckIn->member) {
            throw new \InvalidArgumentException('MemberCheckIn must have a member relationship');
        }

        return new self(
            id: $memberCheckIn->id,
            scanned_at: $memberCheckIn->scanned_at,
            location: $memberCheckIn->location,
            notes: $memberCheckIn->notes,
            device_identifier: $memberCheckIn->device_identifier,
            membership_data: $memberCheckIn->membership_data,
            event_id: $memberCheckIn->event_id,
            event_occurrence_id: $memberCheckIn->event_occurrence_id,
            member: UserData::from([
                'id' => $memberCheckIn->member->id,
                'name' => $memberCheckIn->member->name,
                'email' => $memberCheckIn->member->email,
            ]),
            scanner: $memberCheckIn->scanner ? UserData::from([
                'id' => $memberCheckIn->scanner->id,
                'name' => $memberCheckIn->scanner->name,
                'email' => $memberCheckIn->scanner->email,
            ]) : null,
            event: $memberCheckIn->event ? EventData::from([
                'id' => $memberCheckIn->event->id,
                'name' => $memberCheckIn->event->name,
                'organizer_id' => $memberCheckIn->event->organizer_id,
            ]) : null,
            event_occurrence: $memberCheckIn->eventOccurrence ? EventOccurrenceData::from([
                'id' => $memberCheckIn->eventOccurrence->id,
                'name' => $memberCheckIn->eventOccurrence->name,
                'start_at' => $memberCheckIn->eventOccurrence->start_at,
                'end_at' => $memberCheckIn->eventOccurrence->end_at,
                'venue_name' => $memberCheckIn->eventOccurrence->venue_name,
            ]) : null,
        );
    }
}

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
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
