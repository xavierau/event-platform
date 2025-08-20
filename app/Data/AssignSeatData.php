<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AssignSeatData extends Data
{
    public function __construct(
        public readonly string $seat_number,
        public readonly int $booking_id,
    ) {}

    public static function rules(): array
    {
        return [
            'seat_number' => 'required|string|max:20',
            'booking_id' => 'required|exists:bookings,id',
        ];
    }
}