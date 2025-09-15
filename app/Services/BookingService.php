<?php

namespace App\Services;

use App\Actions\Booking\UpdateBookingAction;
use App\Actions\Booking\UpsertBookingAction;
use App\DataTransferObjects\Booking\InitiateBookingData;
use App\DataTransferObjects\BookingData;
// use App\Models\Order; // Assuming you have an Order model (ORD-001)
use App\Enums\BookingStatusEnum; // Using Transaction model instead of Order
use App\Enums\TransactionStatusEnum; // Assuming you have a Booking model (ORD-002)
use App\Exceptions\InventoryUnavailableException; // Assuming TicketDefinition model (TCKD-001)
use App\Helpers\QrCodeHelper; // Assuming EventOccurrence model (EVT-002)
use App\Models\Booking; // Import the custom exception
use App\Models\Event; // Import for quantity validation issues
// use App\Actions\Bookings\CreateBookingAction; // As per TRX-004
// use App\Actions\Orders\CreateOrderAction; // If you have a separate action for orders
use App\Models\EventOccurrence;
use App\Models\Organizer;
use App\Models\TicketDefinition;
use App\Models\Transaction; // Assuming BookingData DTO will be created
use App\Models\User; // Assuming UpsertBookingAction will be created
use Illuminate\Database\Eloquent\Collection;
// To get the authenticated user if needed directly
use Illuminate\Pagination\LengthAwarePaginator; // Import the QrCodeHelper
use Illuminate\Support\Facades\DB; // Assuming Event model exists
use Illuminate\Support\Facades\Log; // Added Organizer model
use Illuminate\Validation\ValidationException; // Import the UpdateBookingAction
use Stripe\Exception\ApiErrorException;

class BookingService
{
    public function getPaginatedBookings(array $filters = []): LengthAwarePaginator
    {
        $paginated = $this->getAllBookingsWithFilters($filters);

        return $this->transformPaginatedBookings($paginated);
    }

    public function getPaginatedBookingsForOrganizer(User $organizer, array $filters = []): LengthAwarePaginator
    {
        $paginated = $this->getBookingsForOrganizerEventsWithFilters($organizer, $filters);

        return $this->transformPaginatedBookings($paginated);
    }

    protected function transformPaginatedBookings(LengthAwarePaginator $paginated): LengthAwarePaginator
    {
        return $paginated->through(fn (Booking $booking) => [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'event_name' => $booking->event?->getTranslation('name', app()->getLocale()),
            'user_name' => $booking->user?->name,
            'user_email' => $booking->user?->email,
            'status' => $booking->status->label(),
            'status_value' => $booking->status->value,
            'created_at' => $booking->created_at->toIso8601String(),
            'total_price_formatted' => $booking->total_price_formatted,
            'transaction' => $booking->transaction ? [
                'id' => $booking->transaction->id,
                'payment_gateway' => $booking->transaction->payment_gateway,
                'status' => $booking->transaction->status->label(),
                'status_value' => $booking->transaction->status->value,
            ] : null,
        ]);
    }

    /**
     * Processes the booking initiation request.
     *
     * - Validates ticket availability and calculates final prices.
     * - Creates Order and Booking records with an initial status.
     * - If payment is required, initiates Stripe Checkout and returns the URL.
     * - If free, confirms the booking and returns a success message.
     *
     * @return array an array containing keys like 'requires_payment', 'checkout_url', 'booking_id', 'message'
     *
     * @throws \Illuminate\Validation\ValidationException | \App\Exceptions\InventoryUnavailableException | \Exception
     */
    public function processBookingInitiation(User $user, InitiateBookingData $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $occurrence = EventOccurrence::with('event')->findOrFail($data->occurrence_id);
            $totalAmount = 0;
            $currency = null; // Should be determined from tickets or event settings
            $lineItemsForStripe = [];
            $createdBookings = [];

            // 1. Validate items, check availability, calculate total price
            // This is a crucial step. You need to fetch each ticket_definition from the DB,
            // verify its price (don't trust price_at_purchase from DTO for final calculation),
            // check stock (if applicable), and apply any business rules.

            // --- DEBUGGING ---
            if ($data->items === null) {
                dd(
                    'DEBUG: $data->items is NULL'
                );
            } else {
                // Keeping the detailed dd() for now, but commenting it out if the workaround works.
                /* dd(
                'DEBUG: $data->items class is: ' . get_class($data->items),
                'DEBUG: method_exists($data->items, \'isEmpty\'): ' . (method_exists($data->items, 'isEmpty') ? 'true' : 'false'),
                'DEBUG: method_exists($data->items, \'first\'): ' . (method_exists($data->items, 'first') ? 'true' : 'false'),
                'DEBUG: method_exists($data->items, \'getDataClass\'): ' . (method_exists($data->items, 'getDataClass') ? 'true' : 'false'),
                $data->items
                ); */
            }
            // --- END DEBUGGING ---

            if (count($data->items) === 0) {
                // This logic needs to be robust based on your event/ticket structure.
                // If the occurrence itself can be marked as free and allows booking without specific tickets:
                // For this example, we assume if items are empty, it's a free scenario if the occurrence supports it.
                // if (!$occurrence->allow_free_general_admission) { // You'd need such a field or logic
                //     throw ValidationException::withMessages(['items' => 'No items selected for this event occurrence.']);
                // }
                $totalAmount = 0;
                $currency = strtolower(config('cashier.currency'));
            } else {
                foreach ($data->items as $itemData) {
                    $ticketDefinition = TicketDefinition::findOrFail($itemData->ticket_id);
                    $requestedQuantity = $itemData->quantity;

                    // 1. Min/Max per order validation
                    if ($ticketDefinition->min_per_order && $requestedQuantity < $ticketDefinition->min_per_order) {
                        throw ValidationException::withMessages([
                            'items.'.$itemData->ticket_id => "Minimum quantity for {$ticketDefinition->name} is {$ticketDefinition->min_per_order}.",
                        ]);
                    }
                    if ($ticketDefinition->max_per_order && $requestedQuantity > $ticketDefinition->max_per_order) {
                        throw ValidationException::withMessages([
                            'items.'.$itemData->ticket_id => "Maximum quantity for {$ticketDefinition->name} is {$ticketDefinition->max_per_order}.",
                        ]);
                    }

                    // 2. Inventory Check (using the accessor from TicketDefinition model)
                    // Assumes $ticketDefinition->quantity_available accessor exists and provides current, accurate available stock.
                    // This also assumes that if quantity_available is null, it means infinite stock.
                    if (isset($ticketDefinition->quantity_available) && $requestedQuantity > $ticketDefinition->quantity_available) {
                        throw new InventoryUnavailableException(
                            "Not enough stock for ticket: {$ticketDefinition->name}. Requested: {$requestedQuantity}, Available: {$ticketDefinition->quantity_available}."
                        );
                    }
                    // If $ticketDefinition->quantity_available is null, we assume infinite stock and skip the check.

                    // Use DB price, not $itemData->price_at_purchase for calculation integrity
                    $itemTotal = $ticketDefinition->price * $requestedQuantity;
                    $totalAmount += $itemTotal;
                    $currency = strtolower($ticketDefinition->currency); // Assuming all items in a booking share currency

                    $productDescription = $ticketDefinition->description;
                    if (empty(trim((string) $productDescription))) { // Check if null, empty, or just whitespace
                        $fallbackDescription = trim($occurrence->event->name.' - '.$occurrence->name);
                        if (empty($fallbackDescription)) {
                            $productDescription = 'Event Ticket'; // Generic fallback
                        } else {
                            $productDescription = $fallbackDescription;
                        }
                    }

                    $lineItemsForStripe[] = [
                        'price_data' => [
                            'currency' => $currency,
                            'unit_amount' => $ticketDefinition->price, // Price from DB, in cents
                            'product_data' => [
                                'name' => $ticketDefinition->name,
                                'description' => $productDescription, // Use the validated/fallback description
                            ],
                        ],
                        'quantity' => $requestedQuantity,
                    ];
                }
            }

            // Ensure currency is set if not already (e.g. for completely free booking with no items)
            if (is_null($currency)) {
                $currency = strtolower(config('cashier.currency'));
            }

            // 2. Create Order record (if your structure uses one)
            // $order = $this->createOrderAction->execute($user, $totalAmount, $currency, ...);
            // For simplicity, let's assume an Order model exists and we create it directly or via a simpler service method.
            // The Order would also get a status, e.g., TransactionStatusEnum::PENDING_PAYMENT or TransactionStatusEnum::CONFIRMED

            // Mock Order ID for now if not implementing full Order model yet
            $orderId = null; // Replace with actual Order ID if using an Order model
            $transactionStatus = ($totalAmount > 0) ? TransactionStatusEnum::PENDING_PAYMENT : TransactionStatusEnum::CONFIRMED;

            // Determine initial booking status based on transaction status
            $initialBookingStatus = ($transactionStatus === TransactionStatusEnum::CONFIRMED)
                ? BookingStatusEnum::CONFIRMED
                : BookingStatusEnum::PENDING_CONFIRMATION;

            // Example: Creating a simplified Order concept (you should adapt this to your actual Order model and logic)
            // This is highly simplified. Your actual Order creation would be more robust.
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount, // Total amount in cents
                'currency' => $currency,
                'status' => $transactionStatus, // Use the determined transaction status
                // Add other relevant fields: billing_address_id, shipping_address_id, payment_gateway_details etc.
            ]);
            $orderId = $transaction->id; // Use transaction ID as orderId for consistency in this context

            // 3. Create Booking records for each item
            // Link them to the Order if applicable
            if (count($data->items) > 0) {
                foreach ($data->items as $itemData) {
                    $ticketDefinition = TicketDefinition::findOrFail($itemData->ticket_id); // Fetch ticket definition once per item type
                    for ($i = 0; $i < $itemData->quantity; $i++) { // Loop for each unit of this ticket type
                        // This would ideally use a CreateBookingAction as per TRX-004
                        $qrCodeIdentifier = QrCodeHelper::generate();
                        $booking = Booking::create([
                            'user_id' => $user->id,
                            'transaction_id' => $orderId,
                            'event_id' => $occurrence->event->id,
                            'ticket_definition_id' => $itemData->ticket_id,
                            'qr_code_identifier' => $qrCodeIdentifier, // Generate BK- format QR code
                            'booking_number' => $qrCodeIdentifier, // Generate BK- format QR code
                            'quantity' => 1, // Each booking record represents one physical ticket
                            'price_per_unit' => $ticketDefinition->price, // Price from DB
                            'price_at_booking' => $ticketDefinition->price, // Adding the missing price_at_booking field
                            'currency_at_booking' => $currency, // Adding the missing currency_at_booking field
                            'total_price' => $ticketDefinition->price, // Price for one unit
                            'currency' => $currency,
                            'status' => $initialBookingStatus, // Use the determined initial booking status
                            'max_allowed_check_ins' => $ticketDefinition->max_check_ins ?? 1, // Set max check-ins from ticket definition
                            // Consider adding a unique reference/seat number here if applicable per ticket
                        ]);
                        $createdBookings[] = $booking;
                    }
                }
            } elseif ($totalAmount == 0) { // Handle general free admission booking if items list is empty
                // Potentially update Order and Booking statuses to CONFIRMED if they were PENDING_CONFIRMATION
                // For now, we set them to CONFIRMED directly above.
                // TODO: Decrement inventory for free tickets here if not handled by an event listener on Booking creation.
                // For example:
                // if (count($data->items) > 0) { // WORKAROUND using count()
                //    foreach ($data->items as $itemData) {
                //        $ticketDef = TicketDefinition::find($itemData->ticket_id);
                //        if ($ticketDef && $ticketDef->total_quantity !== null) {
                //            $ticketDef->decrement('total_quantity', $itemData->quantity);
                //        }
                //    }
                // }
                $qrCodeIdentifier = QrCodeHelper::generate();

                Booking::create([
                    'user_id' => $user->id,
                    'transaction_id' => $orderId,
                    'event_id' => $occurrence->event->id,
                    'qr_code_identifier' => $qrCodeIdentifier,
                    'booking_number' => $qrCodeIdentifier, // Generate BK- format QR code
                    'ticket_definition_id' => null, // No specific ticket
                    'quantity' => 1, // Or based on some logic for general admission
                    'price_per_unit' => 0,
                    'total_price' => 0,
                    'price_at_booking' => 0, // Free booking
                    'currency_at_booking' => $currency, // Adding the missing currency_at_booking field
                    'currency' => $currency,
                    'status' => BookingStatusEnum::CONFIRMED, // Directly confirmed for free general admission
                    'max_allowed_check_ins' => 1, // Default to 1 for free bookings
                ]);
            }

            // 4. If total amount is 0 (free booking)
            if ($totalAmount <= 0) {
                // Potentially update Order and Booking statuses to CONFIRMED if they were PENDING_CONFIRMATION
                // For now, we set them to CONFIRMED directly above.
                // TODO: Decrement inventory for booked tickets if applicable.
                return [
                    'requires_payment' => false,
                    'booking_confirmed' => true,
                    'booking_id' => $orderId, // Or a specific primary booking ID if no Order model
                    // 'message' => 'Your free booking is confirmed.'
                ];
            }

            // 5. If payment is required, initiate Stripe Checkout session
            $metadata = [
                'transaction_id' => $orderId, // Crucial for webhook reconciliation. This IS the transaction_id.
                // Add any other relevant metadata: occurrence_id, user_id (though user is on Stripe session too)
            ];

            try {

                $params = [
                    'allow_promotion_codes' => true,
                    'success_url' => route('payment.success').'?transaction_id='.$orderId.'&session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => route('payment.cancel').'?transaction_id='.$orderId.'&session_id={CHECKOUT_SESSION_ID}',
                    'metadata' => $metadata,
                    'phone_number_collection' => ['enabled' => true],
                    // 'payment_method_types' => ['card'], // From your PaymentController - usually not needed as Stripe handles this
                ];

                $checkoutSession = $user->checkout($lineItemsForStripe, $params);

                if (! $checkoutSession || ! $checkoutSession->id) {
                    throw new \RuntimeException('Failed to create Stripe checkout session: Invalid session response');
                }

                $transaction->payment_gateway = 'stripe';
                $transaction->payment_gateway_transaction_id = $checkoutSession->id;
                $transaction->save();

                return [
                    'requires_payment' => true,
                    'checkout_url' => $checkoutSession->url,
                    'booking_id' => $orderId, // Or primary booking ID
                    'allow_promotion_codes' => true,
                ];
            } catch (ApiErrorException $e) {
                Log::error('Stripe API Error during checkout session creation in BookingService: '.$e->getMessage(), ['order_id' => $orderId]);
                // Rollback or mark order/bookings as failed if appropriate here, though transaction should handle DB rollback.
                // For the client, rethrow or throw a custom exception that BookingController can catch.
                throw $e; // Rethrow for BookingController to handle the response
            } catch (\Exception $e) {
                Log::error('General error during payment initiation in BookingService: '.$e->getMessage(), ['order_id' => $orderId]);
                throw $e; // Rethrow
            }
        });
    }

    /**
     * Get all bookings. Potentially for admin users.
     */
    public function getAllBookings(): Collection
    {
        // return Booking::all(); // Or with pagination: Booking::paginate()
        return Booking::with(['user', 'event'])->get(); // Example: eager load user and event
    }

    /**
     * Get bookings for a specific user.
     */
    public function getBookingsForUser(User $user): Collection
    {
        // Get bookings through the transaction relationship
        return Booking::whereHas('transaction', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->with([
            'ticketDefinition.eventOccurrences.event',
            'ticketDefinition.eventOccurrences.venue',
            'transaction',
        ])->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get bookings for events managed by a specific organizer.
     */
    public function getBookingsForOrganizerEvents(User $organizer): Collection
    {
        // Get all organizer entities that this user belongs to
        $userOrganizerIds = Organizer::whereHas('users', function ($query) use ($organizer) {
            $query->where('user_id', $organizer->id);
        })->pluck('id');

        // Get bookings for events belonging to any of the user's organizer entities
        return Booking::whereHas('event', function ($query) use ($userOrganizerIds) {
            $query->whereIn('organizer_id', $userOrganizerIds);
        })
            ->with(['user', 'event']) // Eager load related user and event for each booking
            ->get();
    }

    /**
     * Find a specific booking by ID.
     */
    public function findBooking(int $bookingId): ?Booking
    {
        return Booking::with(['user', 'event'])->find($bookingId);
    }

    /**
     * Delete a booking.
     */
    public function deleteBooking(int $bookingId): bool
    {
        $booking = $this->findBooking($bookingId);
        if (! $booking) {
            return false; // Or throw an exception
        }

        return $booking->delete();
    }

    /**
     * Get all bookings with enhanced filtering, searching, and pagination for admin interface.
     */
    public function getAllBookingsWithFilters(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = Booking::with([
            'user', // Remove column selection to avoid hasOneThrough ambiguity
            'event:id,name',
            'ticketDefinition:id,name,price,currency',
            'transaction:id,total_amount,currency,status,payment_gateway,payment_gateway_transaction_id,payment_intent_id,created_at',
        ]);

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                    ->orWhere('qr_code_identifier', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('event', function ($eventQuery) use ($search) {
                        $eventQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Apply status filter
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Apply date range filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Apply event filter
        if (! empty($filters['event_id'])) {
            $query->where('event_id', $filters['event_id']);
        }

        // Apply user filter
        if (! empty($filters['user_id'])) {
            $query->whereHas('transaction', function ($transactionQuery) use ($filters) {
                $transactionQuery->where('user_id', $filters['user_id']);
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    /**
     * Get bookings for organizer's events with enhanced filtering and pagination.
     */
    public function getBookingsForOrganizerEventsWithFilters(User $organizer, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator
    {
        // Get all organizer entities that this user belongs to
        $userOrganizerIds = Organizer::whereHas('users', function ($query) use ($organizer) {
            $query->where('user_id', $organizer->id);
        })->pluck('id');

        // Get event IDs for events belonging to any of the user's organizer entities
        $organizerEventIds = Event::whereIn('organizer_id', $userOrganizerIds)->pluck('id');

        $query = Booking::with([
            'user', // Remove column selection to avoid hasOneThrough ambiguity
            'event:id,name',
            'ticketDefinition:id,name,price,currency',
            'transaction:id,total_amount,currency,status,payment_gateway,payment_gateway_transaction_id,payment_intent_id,created_at,updated_at',
        ])
            ->whereIn('event_id', $organizerEventIds);

        // Apply the same filters as getAllBookingsWithFilters but scoped to organizer's events
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                    ->orWhere('qr_code_identifier', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('event', function ($eventQuery) use ($search) {
                        $eventQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['event_id'])) {
            if (in_array($filters['event_id'], $organizerEventIds->toArray())) {
                $query->where('event_id', $filters['event_id']);
            } else {
                // If organizer tries to filter by an event they don't own, return no results
                $query->where('event_id', -1); // Impossible condition to return empty results
            }
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($filters['per_page'] ?? 15)->withQueryString();
    }

    /**
     * Get detailed booking information including related models.
     */
    public function getDetailedBooking(int $bookingId): ?Booking
    {
        return Booking::with([
            'user', // Remove column selection to avoid hasOneThrough ambiguity
            'event:id,name,description',
            'ticketDefinition:id,name,description,price,currency,total_quantity',
            'ticketDefinition.eventOccurrences:id,event_id,venue_id,name,description,start_at_utc,end_at_utc,timezone,status,capacity,is_online,online_meeting_link',
            'ticketDefinition.eventOccurrences.venue:id,name,address_line_1,city,postal_code',
            'transaction:id,total_amount,currency,status,payment_gateway,payment_gateway_transaction_id,payment_intent_id,created_at,updated_at',
            'checkInLogs:id,booking_id,method,check_in_timestamp,operator_user_id,device_identifier,location_description,status',
            'checkInLogs.operator:id,name',
        ])->find($bookingId);
    }

    /**
     * Get events list for filter dropdown (admin scope).
     */
    public function getEventsForFilter(): Collection
    {
        return Event::select('id', 'name')
            ->whereHas('bookings')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get events list for filter dropdown (organizer scope).
     */
    public function getOrganizerEventsForFilter(User $organizer): Collection
    {
        // Get all organizer entities that this user belongs to
        $userOrganizerIds = Organizer::whereHas('users', function ($query) use ($organizer) {
            $query->where('user_id', $organizer->id);
        })->pluck('id');

        return Event::select('id', 'name')
            ->whereIn('organizer_id', $userOrganizerIds)
            ->whereHas('bookings')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get booking statistics for dashboard.
     */
    public function getBookingStatistics(?User $organizer = null): array
    {
        $baseQuery = Booking::query();

        if ($organizer) {
            // Get all organizer entities that this user belongs to
            $userOrganizerIds = Organizer::whereHas('users', function ($query) use ($organizer) {
                $query->where('user_id', $organizer->id);
            })->pluck('id');

            // Get event IDs for events belonging to any of the user's organizer entities
            $organizerEventIds = Event::whereIn('organizer_id', $userOrganizerIds)->pluck('id');
            $baseQuery->whereIn('event_id', $organizerEventIds);
        }

        // Calculate revenue directly from the bookings query
        $revenueQuery = (clone $baseQuery)->where('status', BookingStatusEnum::CONFIRMED);

        $totalRevenueByCurrency = $revenueQuery
            ->select(DB::raw('currency_at_booking, SUM(price_at_booking) as total'))
            ->groupBy('currency_at_booking')
            ->pluck('total', 'currency_at_booking')
            ->map(fn ($total) => (int) $total) // Cast to integer
            ->all();

        return [
            'total_bookings' => (clone $baseQuery)->count(),
            'confirmed_bookings' => (clone $baseQuery)->where('status', BookingStatusEnum::CONFIRMED)->count(),
            'pending_bookings' => (clone $baseQuery)->where('status', BookingStatusEnum::PENDING_CONFIRMATION)->count(),
            'used_bookings' => (clone $baseQuery)->where('status', BookingStatusEnum::USED)->count(),
            'cancelled_bookings' => (clone $baseQuery)->where('status', BookingStatusEnum::CANCELLED)->count(),
            'total_revenue' => $totalRevenueByCurrency,
            'default_currency' => config('currency.default', 'USD'),
            'recent_bookings' => (clone $baseQuery)
                ->with(['user', 'event:id,name']) // Remove column selection from user relationship
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    // TODO: Add other methods like cancelBooking, updateBookingStatus, etc.
}
