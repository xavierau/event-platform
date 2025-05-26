<?php

namespace App\DataTransferObjects\Booking;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;

// This class represents a single item in the booking request
class BookingRequestItemData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('ticket_definitions', 'id')] // Assuming your table is ticket_definitions
        public int $ticket_id,
        #[Required, IntegerType, Min(1)]
        public int $quantity,
        #[Required, Numeric, Min(0)] // Price at time of booking, can be 0 for free tickets
        public float $price_at_purchase,
        #[Required, StringType]
        public string $name // Name of the ticket for reference, backend should still verify price against ticket_id
    ) {}
}

// This is the main DTO for the initiate booking request
class InitiateBookingData extends Data
{
    public function __construct(
        #[Required, IntegerType, Exists('event_occurrences', 'id')] // Assuming your table is event_occurrences
        public int $occurrence_id,
        /** @var DataCollection<BookingRequestItemData> */
        #[Required, ArrayType, Min(0)] // Min(0) allows empty items array for free "event-level" bookings if applicable
        // Or Min(1) if at least one item is always required.
        // The Vue component currently checks bookingItems.length === 0 && totalPrice > 0
        // which implies free events still send an empty items array if no specific tickets chosen (if that's a scenario)
        // Let's assume Min(0) for now, but if items are always required, change to Min(1).
        public DataCollection $items,
    ) {}

    // You can add custom validation logic if needed, for example:
    // public static function rules(): array
    // {
    //     return [
    //         'items' => function ($attribute, $value, $fail) {
    //             if (empty($value) && SomeBusinessLogicToCheckIfTotalPriceBasedOnOccurrenceAndItemsIsZero()) {
    //                 // Potentially allow empty items if it's a truly free scenario
    //                 // Otherwise, if items are expected for calculating price:
    //                 // $fail('The ' . $attribute . ' cannot be empty unless it is a free booking with no specific ticket types.');
    //             }
    //         },
    //     ];
    // }
}
