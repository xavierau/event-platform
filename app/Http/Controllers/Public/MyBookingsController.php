<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Enums\BookingStatusEnum;

class MyBookingsController extends Controller
{
    protected BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    /**
     * Display the user's bookings page.
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Get bookings for the authenticated user with necessary relationships
        $bookings = $this->bookingService->getBookingsForUser($user)
            ->filter(function ($booking) {
                return $booking->status === BookingStatusEnum::CONFIRMED || $booking->status === BookingStatusEnum::USED;
            });

        // Transform the bookings to include the necessary data for the frontend
        // Note: Bookings are associated with ticket_definition_id only
        // Event occurrences are accessed through the ticket definition (many-to-many relationship)
        $transformedBookings = $bookings->map(function ($booking) {
            // Get all event occurrences for this ticket
            $eventOccurrences = $booking->ticketDefinition?->eventOccurrences;
            $eventOccurrence = $eventOccurrences?->first();
            $event = $eventOccurrence?->event;

            return [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'quantity' => $booking->quantity,
                'total_price' => $booking->price_at_booking, // Using price_at_booking as total_price for single ticket
                'currency' => $booking->currency_at_booking,
                'status' => $booking->status,
                'created_at' => $booking->created_at->toISOString(),
                'qr_code_identifier' => $booking->qr_code_identifier,
                'ticket_definition' => $booking->ticketDefinition ? [
                    'name' => is_array($booking->ticketDefinition->name)
                        ? ($booking->ticketDefinition->name[app()->getLocale()] ?? $booking->ticketDefinition->name['en'] ?? 'General Admission')
                        : $booking->ticketDefinition->name,
                    'price' => $booking->ticketDefinition->price,
                    'currency' => $booking->ticketDefinition->currency,
                    'quantity' => $booking->quantity,
                    'total_price' => $booking->price_at_booking,
                ] : null,
                'event_occurrences' => $eventOccurrences ? $eventOccurrences->map(function ($occurrence) {
                    return [
                        'id' => $occurrence->id,
                        'name' => is_array($occurrence->name)
                            ? ($occurrence->name[app()->getLocale()] ?? $occurrence->name['en'] ?? 'Event Occurrence')
                            : $occurrence->name,
                        'start_at' => $occurrence->start_at_utc?->toISOString(),
                        'end_at' => $occurrence->end_at_utc?->toISOString(),
                        'venue_name' => $occurrence->venue ? (
                            is_array($occurrence->venue->name)
                            ? ($occurrence->venue->name[app()->getLocale()] ?? $occurrence->venue->name['en'] ?? 'Venue')
                            : $occurrence->venue->name
                        ) : null,
                        'venue_address' => $occurrence->venue ? $occurrence->venue->address : null,
                    ];
                })->toArray() : [],
                'event' => $event ? [
                    'name' => is_array($event->name)
                        ? ($event->name[app()->getLocale()] ?? $event->name['en'] ?? 'Unknown Event')
                        : $event->name,
                ] : null,
            ];
        });

        return Inertia::render('Public/MyBookings', [
            'bookings' => $transformedBookings->values()->toArray(),
        ]);
    }
}
